<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    public function products()
    {
        return $this->hasMany(Product::class);
    } 

    /**
     * Get the category's active status as boolean.
     */
    public function getIsActiveAttribute($value)
    {
        return (bool) $value;
    }

    /**
     * Set the category's active status as integer for database.
     */
    public function setIsActiveAttribute($value)
    {
        $this->attributes['is_active'] = $value ? 1 : 0;
    }
}
