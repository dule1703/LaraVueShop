<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**     
     * @var list<string>
     */
    protected $fillable = [
        'name',          
        'first_name',
        'last_name',
        'email',
        'password',
        'role'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relacija sa Cart modelom     
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Sačuvane adrese isporuke. Svaki pristup pojedinačnoj adresi ide kroz ovu
     * relaciju ($user->addresses()->findOrFail($id)), nikad Address::find() —
     * IDOR zaštita (tuđa adresa izgleda isto kao nepostojeća).
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}