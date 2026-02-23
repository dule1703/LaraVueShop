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
        'customer_email',
    ];

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