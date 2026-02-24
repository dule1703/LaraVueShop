<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalController extends Controller
{
    private $provider;

    public function __construct()
    {
        $this->provider = new PayPalClient;
        $this->provider->setApiCredentials(config('paypal'));
        $this->provider->getAccessToken();
    }

    public function createPayment($orderId)
    {
        $orderId = (int) $orderId;
        $order = Order::findOrFail($orderId);

        // Provera postojećeg plaćanja
        if ($order->payment) {
            if ($order->payment->status === 'completed') {
                return redirect()->route('order.success', $order->id)
                    ->with('success', 'Plaćanje već obavljeno.');
            }
            if ($order->payment->status === 'pending') {
                return redirect()->back()
                    ->with('error', 'Plaćanje već pokrenuto.');
            }
        }

        try {
            // Kreiranje PayPal order-a
            $response = $this->provider->createOrder([
                "intent" => "CAPTURE",
                "purchase_units" => [
                    [
                        "reference_id" => "order_" . $order->id,
                        "amount" => [
                            "currency_code" => "EUR",
                            "value" => number_format($order->total_price, 2, '.', ''),
                            "breakdown" => [
                                "item_total" => [
                                    "currency_code" => "EUR",
                                    "value" => number_format($order->total_price, 2, '.', '')
                                ]
                            ]
                        ],
                        "description" => "Porudžbina #" . $order->id,
                        "custom_id" => (string) $order->id,
                    ]
                ],
                "application_context" => [
                    "return_url" => route('paypal.success', $order->id),
                    "cancel_url" => route('paypal.cancel', $order->id),
                    "brand_name" => env('APP_NAME', 'LaraVueShop'),
                    "locale" => "sr-RS",
                    "user_action" => "PAY_NOW"
                ]
            ]);

            Log::info('PayPal create response', [
                'order_id' => $order->id,
                'response' => $response,
            ]);

            // Provera da li je order kreiran
            if (isset($response['id']) && $response['id'] != null) {
                $paypalOrderId = $response['id'];

                // Kreiraj payment record
                if (!$order->payment) {
                    $order->payment()->create([
                        'provider' => 'paypal',
                        'provider_payment_id' => $paypalOrderId,
                        'amount' => $order->total_price,
                        'currency' => 'EUR',
                        'status' => 'pending',
                    ]);
                } else {
                    $order->payment->update([
                        'provider_payment_id' => $paypalOrderId,
                        'status' => 'pending',
                    ]);
                }

                // Pronađi approve link
                $approvalLink = null;
                foreach ($response['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approvalLink = $link['href'];
                        break;
                    }
                }

                if ($approvalLink) {
                    return redirect($approvalLink);
                }
            }

            throw new \Exception('Nije moguće kreirati PayPal order: ' . json_encode($response));

        } catch (\Exception $e) {
            Log::error('PayPal create error', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Greška sa PayPal-om: ' . $e->getMessage());
        }
    }

    public function success(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $paypalOrderId = $request->query('token');

        if (!$paypalOrderId) {
            return redirect()->route('checkout')->with('error', 'Nedostaje PayPal token.');
        }

        try {
            // Capture payment
            $response = $this->provider->capturePaymentOrder($paypalOrderId);

            Log::info('PayPal capture response', [
                'order_id' => $orderId,
                'paypal_order_id' => $paypalOrderId,
                'response' => $response,
            ]);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                // Ažuriraj payment i order
                if ($order->payment) {
                    $order->payment->update([
                        'status' => 'completed',
                        'payload' => json_encode($response),
                        'paid_at' => now(),
                    ]);
                }
                
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                return redirect()->route('order.success', $order->id)
                    ->with('success', 'Plaćanje uspešno! Hvala na porudžbini.');
            }

            throw new \Exception('Capture nije uspešan: ' . json_encode($response));

        } catch (\Exception $e) {
            Log::error('PayPal capture error', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            if ($order->payment) {
                $order->payment->update(['status' => 'failed']);
            }
            $order->update(['status' => 'failed']);

            return redirect()->route('payment.failed', $order->id)
                ->with('error', 'Plaćanje nije moglo biti završeno.');
        }
    }

    public function cancel(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);

        Log::info('PayPal payment cancelled', ['order_id' => $orderId]);

        if ($order->payment) {
            $order->payment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }
        
        $order->update(['status' => 'cancelled']);

        return redirect()->route('checkout')->with('error', 'Plaćanje je otkazano.');
    }
}