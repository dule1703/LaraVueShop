<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Ulogovan korisnik vidi samo svoju porudžbinu; gost vidi samo porudžbine
     * koje je sam napravio u tekućoj sesiji (id upisan pri OrderController::store).
     */
    public function view(?User $user, Order $order): bool
    {
        if ($user) {
            return $order->user_id === $user->id;
        }

        return in_array($order->id, session('guest_order_ids', []), true);
    }
}
