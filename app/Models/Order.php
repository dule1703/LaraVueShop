<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'address',
        'city',
        'postal_code',
        'phone',
        'notes',
        'total_price',
        'status',
        'payment_method',
        'customer_email',
        'shipping_recipient_name',
        'shipping_phone',
        'shipping_line1',
        'shipping_line2',
        'shipping_city',
        'shipping_postal_code',
        'shipping_country',
        'shipping_address_id',
    ];

    /**
     * Samo audit trag (koja sačuvana adresa je izabrana). Za prikaz porudžbine
     * se NIKAD ne koristi — izvor istine su shipping_* snapshot kolone.
     */
    public function shippingAddress()
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
    
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            trim("{$this->postal_code} {$this->city}"),
            $this->phone ? 'Tel: ' . $this->phone : null,
            $this->notes,
        ]);
        return implode("\n", $parts);
    }
}