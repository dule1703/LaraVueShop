<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sačuvana adresa isporuke korisnika. Porudžbina NIKAD ne čita adresu odavde
 * za prikaz — orders.shipping_* je snapshot u trenutku porudžbine.
 * Sva upisivanja idu kroz App\Services\AddressService (jedan put za
 * pravilo "samo jedna podrazumevana adresa po korisniku").
 */
class Address extends Model
{
    protected $fillable = [
        'user_id',
        'recipient_name',
        'phone',
        'line1',
        'line2',
        'city',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
