<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    /**
     * Odbrana u dubini: AddressController već traži adresu kroz
     * $user->addresses() (tuđa adresa -> 404 pre policy-ja), ali policy ostaje
     * kao drugi sloj ako se lookup ikad promeni.
     */
    public function view(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }

    public function delete(User $user, Address $address): bool
    {
        return $address->user_id === $user->id;
    }
}
