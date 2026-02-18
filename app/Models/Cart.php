<?php
// app/Models/Cart.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'items',
    ];

    /**     
     * Cast se automatski dekoduje u array kada se čita
     */
    protected $casts = [
        'items' => 'array',
    ];

    /**
     * Relacija sa User modelom
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}