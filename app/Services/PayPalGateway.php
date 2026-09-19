<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalGateway implements PaymentGateway
{
    private ?PayPalClient $client = null;

    public function createOrder(Order $order): array
    {
        return $this->client()->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'order_' . $order->id,
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format($order->total_price, 2, '.', ''),
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'EUR',
                                'value' => number_format($order->total_price, 2, '.', ''),
                            ],
                        ],
                    ],
                    'description' => 'Porudžbina #' . $order->id,
                    'custom_id' => (string) $order->id,
                ],
            ],
            'application_context' => [
                'return_url' => route('paypal.success', $order->id),
                'cancel_url' => route('paypal.cancel', $order->id),
                'brand_name' => env('APP_NAME', 'LaraVueShop'),
                'locale' => 'sr-RS',
                'user_action' => 'PAY_NOW',
            ],
        ]);
    }

    public function captureOrder(string $providerOrderId): array
    {
        return $this->client()->capturePaymentOrder($providerOrderId);
    }

    /**
     * Kredencijali i access token se pribavljaju tek pri prvom stvarnom pozivu
     * (mrežni poziv), ne u konstruktoru — zato je bezbedno resolve-ovati ovu
     * klasu iz kontejnera bez mrežnog poziva, npr. u kontrolerima koji je ne
     * koriste na svakoj ruti.
     */
    private function client(): PayPalClient
    {
        if ($this->client === null) {
            $this->client = new PayPalClient;
            $this->client->setApiCredentials(config('paypal'));
            $this->client->getAccessToken();
        }

        return $this->client;
    }
}
