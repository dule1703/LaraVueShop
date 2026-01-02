<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'is_active',
        'image',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the product's active status as boolean.
     */
    public function getIsActiveAttribute($value)
    {
        return (bool) $value;
    }

    /**
     * Set the product's active status as integer for database.
     */
    public function setIsActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value ? 1 : 0;
    }
}
