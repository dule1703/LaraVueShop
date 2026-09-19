<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * Kreira porudžbinu kod platnog provajdera za datu Order i vraća sirov
     * odgovor provajdera (mora sadržati 'id', a pri uspehu 'links' niz sa
     * approve linkom — isti oblik koji PayPalController::createPayment očekuje).
     */
    public function createOrder(Order $order): array;

    /**
     * Potvrđuje (capture) uplatu kod provajdera za dati provider order id.
     */
    public function captureOrder(string $providerOrderId): array;
}
