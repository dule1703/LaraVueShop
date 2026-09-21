<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bibliografski podaci knjige. Komercijalni podaci (naziv, cena, zalihe, slika,
 * aktivnost, kategorija) ostaju na povezanom Product-u.
 */
class Book extends Model
{
    use HasFactory;

    public const FORMATS = ['hardcover', 'paperback', 'ebook'];

    public const SCRIPTS = ['Cyrl', 'Latn'];

    public const AUTHOR_ROLES = ['author', 'translator', 'illustrator', 'editor'];

    // search_text namerno nije fillable — računa se iz ostalih polja (kasnija faza).
    protected $fillable = [
        'product_id',
        'isbn13',
        'isbn10',
        'publisher_id',
        'subtitle',
        'original_title',
        'published_year',
        'pages',
        'language',
        'script',
        'format',
        'weight_g',
    ];

    protected $casts = [
        'published_year' => 'integer',
        'pages' => 'integer',
        'weight_g' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * Svi saradnici (autori, prevodioci, ilustratori, urednici) po redosledu.
     */
    public function authors()
    {
        return $this->belongsToMany(Author::class)
            ->withPivot('role', 'position')
            ->orderByPivot('position');
    }

    /**
     * Samo saradnici sa ulogom "author".
     */
    public function writers()
    {
        return $this->authors()->wherePivot('role', 'author');
    }
}
