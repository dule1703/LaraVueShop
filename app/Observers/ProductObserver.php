<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\BookSearchIndexer;

/**
 * Knjige se pišu isključivo kroz BookService (koji posle attach/detach autora
 * radi $book->touch(), pa BookObserver ionako preračuna search_text). Ovaj
 * observer je odbrambeni sloj za slučaj da se Product ikad sačuva mimo
 * BookService-a (npr. budući generički Admin\ProductController).
 */
class ProductObserver
{
    public function saved(Product $product): void
    {
        $book = $product->book;

        if ($book !== null) {
            BookSearchIndexer::sync($book);
        }
    }
}
