<?php

namespace Tests\Doubles;

use App\Contracts\PaymentGateway;
use App\Models\Order;

/**
 * Test double za PaymentGateway — omogućava testiranje PayPal toka bez
 * ijednog mrežnog poziva (Faza 0, korak 4 / problem #7 iz CLAUDE.md).
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var int[] */
    public array $createOrderCalls = [];

    /** @var string[] */
    public array $captureOrderCalls = [];

    public function __construct(
        private array $createOrderResponse = [],
        private array $captureOrderResponse = [],
    ) {
    }

    public function createOrder(Order $order): array
    {
        $this->createOrderCalls[] = $order->id;

        return $this->createOrderResponse;
    }

    public function captureOrder(string $providerOrderId): array
    {
        $this->captureOrderCalls[] = $providerOrderId;

        return $this->captureOrderResponse;
    }
}
