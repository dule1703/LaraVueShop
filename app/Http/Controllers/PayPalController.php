<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGateway;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

class PayPalController extends Controller
{
    public function __construct(private PaymentGateway $gateway, private InventoryService $inventory)
    {
    }

    public function createPayment(Order $order)
    {
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
            $response = $this->gateway->createOrder($order);

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

    public function success(Request $request, Order $order)
    {
        $paypalOrderId = $request->query('token');

        if (!$paypalOrderId) {
            return redirect()->route('checkout')->with('error', 'Nedostaje PayPal token.');
        }

        try {
            // Capture payment
            $response = $this->gateway->captureOrder($paypalOrderId);

            Log::info('PayPal capture response', [
                'order_id' => $order->id,
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
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            if ($order->payment) {
                $order->payment->update(['status' => 'failed']);
            }
            $this->inventory->restoreStock($order, 'payment_failed', 'failed');

            return redirect()->route('payment.failed', $order->id)
                ->with('error', 'Plaćanje nije moglo biti završeno.');
        }
    }

    public function cancel(Request $request, Order $order)
    {
        Log::info('PayPal payment cancelled', ['order_id' => $order->id]);

        if ($order->payment) {
            $order->payment->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        $this->inventory->restoreStock($order, 'cancel', 'cancelled');

        return redirect()->route('checkout')->with('error', 'Plaćanje je otkazano.');
    }
}
