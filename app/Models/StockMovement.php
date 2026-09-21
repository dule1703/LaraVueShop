<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Zapis o promeni zaliha (append-only). Logika upisa dolazi u Fazi 5 (inventar).
 */
class StockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const REASONS = ['order', 'cancel', 'payment_failed', 'restock', 'manual'];

    protected $fillable = [
        'product_id',
        'delta',
        'reason',
        'order_id',
        'user_id',
        'note',
    ];

    protected $casts = [
        'delta' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
