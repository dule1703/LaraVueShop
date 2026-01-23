<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use Illuminate\Support\Facades\Redirect;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PayPalController extends Controller
{
    private $client;

    public function __construct()
    {
        $environment = new SandboxEnvironment(
            env('PAYPAL_CLIENT_ID'),
            env('PAYPAL_CLIENT_SECRET')
        );
        $this->client = new PayPalHttpClient($environment);
    }

public function createPayment($orderId)
{
    $orderId = (int) $orderId;
    $order = Order::findOrFail($orderId);

    // 1. Provera da li već postoji plaćanje za ovaj order (sprečava duple upise)
    if ($order->payment) {
        $existingPayment = $order->payment;

        if ($existingPayment->status === 'completed') {
            return redirect()->route('order.success', $order->id)
                ->with('success', 'Plaćanje je već obavljeno za ovu porudžbinu.');
        }

        if ($existingPayment->status === 'pending') {
            return redirect()->back()
                ->with('error', 'Plaćanje je već pokrenuto za ovu porudžbinu. Proverite status ili kontaktirajte podršku.');
        }

        // Ako je 'failed' ili nešto drugo – možeš dozvoliti novi pokušaj ili blokirati
    }

    // 2. Kreiranje PayPal order-a
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');

    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => [
                "currency_code" => "EUR",
                "value" => number_format($order->total_price, 2, '.', '')
            ],
            "description" => "Payment for order #" . $order->id,
        ]],
        "application_context" => [
            "return_url" => route('paypal.success', $order->id),
            "cancel_url" => route('paypal.cancel', $order->id),
            "brand_name" => "LaraVueShop",
            "locale" => "sr-RS", // srpski jezik ako želiš
            "landing_page" => "LOGIN",
            "user_action" => "PAY_NOW"
        ]
    ];

    try {
        $response = $this->client->execute($request);

        // 3. Kreiraj payment record (samo ako ne postoji)
        $order->payment()->create([
            'provider' => 'paypal',
            'provider_payment_id' => $response->result->id,
            'amount' => $order->total_price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        // 4. Pronađi approve link
        $links = $response->result->links ?? [];
        $approvalLink = null;

        foreach ($links as $link) {
            $rel = trim((string) ($link->rel ?? ''));
            if ($rel === 'approve') {
                $approvalLink = $link->href ?? null;
                break;
            }
        }

        if ($approvalLink) {
            return Redirect::away($approvalLink);
        }

        // Ako nema linka – fallback i log
        Log::warning('PayPal approve link not found', [
            'order_id' => $order->id,
            'links' => json_encode($links),
        ]);

        return redirect()->back()->with('error', 'Nije pronađen link za odobravanje plaćanja. Kontaktirajte podršku.');
    } catch (\Exception $e) {
        Log::error('PayPal create payment exception', [
            'order_id' => $order->id,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return redirect()->back()->with('error', 'Greška prilikom kreiranja plaćanja: ' . $e->getMessage());
    }
}

public function success(Request $request, $orderId)
{
    $orderId = (int) $orderId;
    $order = Order::findOrFail($orderId);

    $token = $request->query('token');
    $payerId = $request->query('PayerID'); // PayPal šalje i PayerID

    if (!$token) {
        Log::warning('Missing PayPal token in success callback', [
            'order_id' => $orderId,
            'query' => $request->query(),
        ]);

        return redirect('/checkout')->with('error', 'Nedostaje PayPal token. Plaćanje nije završeno.');
    }

    $captureRequest = new OrdersCaptureRequest($token);

    try {
        $response = $this->client->execute($captureRequest);

        Log::info('PayPal capture response', [
            'order_id' => $orderId,
            'status_code' => $response->statusCode,
            'result' => json_encode($response->result),
        ]);

        if ($response->statusCode === 201) {
            $order->payment->update(['status' => 'completed']);
            $order->update(['status' => 'paid']);

            return redirect()->route('order.success', $order->id)->with('success', 'Plaćanje uspešno obavljeno!');
        } else {
            $order->payment->update(['status' => 'failed']);
            $order->update(['status' => 'failed']);

            return redirect('/checkout')->with('error', 'Plaćanje nije uspešno finalizovano. Status: ' . $response->statusCode);
        }
    } catch (\Exception $e) {
            Log::error('PayPal capture exception', [
                'order_id' => $order->id,
                'token' => $token,
                'payer_id' => $payerId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($order->payment) {
                $order->payment->update(['status' => 'failed']);
            }
            $order->update(['status' => 'failed']);

            // Preusmeri na novu stranicu PaymentFailed
            return redirect()->route('payment.failed', $order->id)
                ->with('error', 'Payment could not be completed. Please try again or contact support.');
        }
}

    public function cancel(Request $request, $orderId)
    {
        $orderId = (int) $orderId;
        $order = Order::findOrFail($orderId);

        $order->payment->update(['status' => 'failed']);
        $order->update(['status' => 'cancelled']);

        return redirect()->route('checkout')->with('error', 'Plaćanje otkazano.');
    }
}