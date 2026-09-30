<?php
// app/Http/Controllers/OrderController.php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Napomena: cena i total_price se NAMERNO ne uzimaju od klijenta.
        // Server ih računa iz baze (products.price) u transakciji ispod.
        $validated = $request->validate([
            'email'        => 'required|email|max:255',
            'notes'        => 'nullable|string',
            'items'        => 'required|array|min:1',
            'items.*.id'   => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:paypal,cod',
            // Adresa isporuke: ILI id sačuvane adrese ulogovanog korisnika, ILI
            // inline shipping.* polja (gost, ili ulogovan korisnik sa novom adresom).
            'address_id'   => 'nullable|integer',
            'save_address' => 'nullable|boolean',
            'shipping'                => 'nullable|array',
            // Checkout.vue UVEK šalje shipping.* preko useForm-a, čak i kad je
            // address_id popunjen i inline polja skrivena — kao prazan string,
            // ne izostavljeno. Laravel-ov default ConvertEmptyStringsToNull
            // middleware to pretvara u null pre validacije; required_without
            // samo govori "nije obavezno", ne i "preskoči ostala pravila" — bez
            // eksplicitnog nullable, 'string' pravilo i dalje puca na null.
            'shipping.recipient_name' => 'nullable|required_without:address_id|string|max:255',
            'shipping.phone'          => 'nullable|required_without:address_id|string|max:50',
            'shipping.line1'          => 'nullable|required_without:address_id|string|max:255',
            'shipping.line2'          => 'nullable|string|max:255',
            'shipping.city'           => 'nullable|required_without:address_id|string|max:255',
            'shipping.postal_code'    => 'nullable|required_without:address_id|string|max:20',
            'shipping.country'        => 'nullable|string|max:255',
        ]);

        // Provera vlasništva adrese PRE bilo kakvog upisa (isti princip "proveri pre
        // nego što išta dirneš" kao i server-side cena). Gost nikad ne sme da
        // referencira address_id (isti IDOR oblik kao Faza 0, problem #5); za
        // ulogovanog korisnika lookup je kroz relaciju — tuđa i nepostojeća adresa
        // daju ISTU poruku, da se ne otkrije koji ID postoji.
        $savedAddress = null;
        $addressId = $validated['address_id'] ?? null;

        if ($addressId !== null) {
            if (Auth::guest()) {
                throw ValidationException::withMessages([
                    'address_id' => 'Adresa je dostupna samo za ulogovane korisnike.',
                ]);
            }

            $savedAddress = Auth::user()->addresses()->find($addressId);

            if (! $savedAddress) {
                throw ValidationException::withMessages([
                    'address_id' => 'Adresa ne postoji ili ne pripada vašem nalogu.',
                ]);
            }
        }

        $shippingSnapshot = $savedAddress
            ? $savedAddress->only(['recipient_name', 'phone', 'line1', 'line2', 'city', 'postal_code', 'country'])
            : [
                'recipient_name' => $validated['shipping']['recipient_name'],
                'phone'          => $validated['shipping']['phone'],
                'line1'          => $validated['shipping']['line1'],
                'line2'          => $validated['shipping']['line2'] ?? null,
                'city'           => $validated['shipping']['city'],
                'postal_code'    => $validated['shipping']['postal_code'],
                'country'        => $validated['shipping']['country'] ?? null,
            ];
        $shippingSnapshot['line2'] = filled($shippingSnapshot['line2'] ?? null) ? $shippingSnapshot['line2'] : null;
        $shippingSnapshot['country'] = filled($shippingSnapshot['country'] ?? null) ? $shippingSnapshot['country'] : 'Srbija';

        // "Sačuvaj kao adresu" ima smisla samo za NOVU inline adresu ulogovanog korisnika.
        $shouldSaveAddress = ! $savedAddress && Auth::check() && ! empty($validated['save_address']);

        $order = DB::transaction(function () use ($validated, $shippingSnapshot, $savedAddress, $shouldSaveAddress) {
            $shippingAddressId = $savedAddress?->id;

            // U ISTOJ transakciji kao porudžbina — ako porudžbina padne (npr. nema
            // zaliha), ni nova sačuvana adresa ne ostaje.
            if ($shouldSaveAddress) {
                $newAddress = app(AddressService::class)->create(
                    Auth::user(),
                    $shippingSnapshot + ['is_default' => true]
                );
                $shippingAddressId = $newAddress->id;
            }

            $totalPrice = 0;
            $orderItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Jedna od knjiga iz korpe više ne postoji.',
                    ]);
                }

                // Atomsko umanjenje zaliha — uspeva samo ako ima dovoljno stanja.
                $affected = Product::where('id', $product->id)
                    ->where('stock', '>=', $item['quantity'])
                    ->update(['stock' => DB::raw('stock - ' . (int) $item['quantity'])]);

                if ($affected === 0) {
                    $currentStock = Product::where('id', $product->id)->value('stock') ?? 0;

                    throw ValidationException::withMessages([
                        'items' => "Nema dovoljno zaliha za knjigu \"{$product->name}\" (na stanju: {$currentStock}, traženo: {$item['quantity']}).",
                    ]);
                }

                $orderItems[] = [
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'product_price' => $product->price,
                    'quantity'      => $item['quantity'],
                ];

                $totalPrice += $product->price * $item['quantity'];
            }

            // Legacy first_name/last_name kolone (Admin/Orders/*.vue i dalje čitaju samo njih
            // direktno, van opsega ovog koraka da se diraju) nemaju odgovarajuće polje u novom
            // Address modelu (samo recipient_name) — naivan split na PRVI razmak, dokumentovano
            // pojednostavljenje (višedelna imena mogu da završe podeljena "pogrešno", npr. "Jovan
            // Petar Jovanović" -> first="Jovan", last="Petar Jovanović" — nema pouzdanog načina
            // da se to razdvoji bez traženja odvojenih first/last polja, što bi kršilo zadati
            // Address data model, gde je recipient_name namerno jedno polje).
            $parts = explode(' ', trim($shippingSnapshot['recipient_name']), 2);
            $legacyFirstName = $parts[0];
            $legacyLastName = $parts[1] ?? '';

            $order = Order::create([
                'user_id'         => Auth::id(),
                // Namerni dual-write: stare kolone se i dalje pune (admin prikaz porudžbina
                // ih čita direktno), nove shipping_* su izvor istine za snapshot.
                'first_name'      => $legacyFirstName,
                'last_name'       => $legacyLastName,
                'address'         => $shippingSnapshot['line1'] . ($shippingSnapshot['line2'] ? ', ' . $shippingSnapshot['line2'] : ''),
                'city'            => $shippingSnapshot['city'],
                'postal_code'     => $shippingSnapshot['postal_code'],
                'phone'           => $shippingSnapshot['phone'],
                'shipping_recipient_name' => $shippingSnapshot['recipient_name'],
                'shipping_phone'          => $shippingSnapshot['phone'],
                'shipping_line1'          => $shippingSnapshot['line1'],
                'shipping_line2'          => $shippingSnapshot['line2'] ?? null,
                'shipping_city'           => $shippingSnapshot['city'],
                'shipping_postal_code'    => $shippingSnapshot['postal_code'],
                'shipping_country'        => $shippingSnapshot['country'],
                // Samo audit trag — prikaz porudžbine čita isključivo shipping_* kolone.
                'shipping_address_id'     => $shippingAddressId,
                'notes'           => $validated['notes'] ?? null,
                'total_price'     => $totalPrice,
                'status'          => 'pending',
                'payment_method'  => $validated['payment_method'],
                'customer_email'  => Auth::check() ? Auth::user()->email : $validated['email'],
            ]);

            foreach ($orderItems as $orderItem) {
                OrderItem::create($orderItem + ['order_id' => $order->id]);

                StockMovement::create([
                    'product_id' => $orderItem['product_id'],
                    'delta'      => -$orderItem['quantity'],
                    'reason'     => 'order',
                    'order_id'   => $order->id,
                    'user_id'    => Auth::id(),
                ]);
            }

            return $order;
        });

        // Gost pamti sopstvenu porudžbinu kroz sesiju — jedini način da kasnije
        // pristupi success/cod-success/payment-failed stranici bez naloga (vidi OrderPolicy).
        if (Auth::guest()) {
            $request->session()->push('guest_order_ids', $order->id);
        }

         // ✅ ISPRAZNI KORPU IZ BAZE
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->cart) {
                $user->cart->delete();
                Log::info('Cart deleted for user after order', [
                    'user_id' => $user->id,
                    'order_id' => $order->id
                ]);
            }
        }

        Log::info('Order created', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'total' => $order->total_price
        ]);

        if ($validated['payment_method'] === 'paypal') {
            Log::info('Redirecting to PayPal for order: ' . $order->id);
            return inertia()->location(route('paypal.createPayment', $order->id));
        } else {
            Log::info('COD order - redirecting to success for order: ' . $order->id);
            return inertia()->location(route('order.cod.success', $order->id));
        }
    }
}