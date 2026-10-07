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

    /**
     * Soft delete proizvoda tvrdo briše povezanu knjigu (author_book ide kaskadno
     * preko FK), da ne ostanu siročad Book redovi koje bi brojali autori/izdavači
     * i koji bi držali ISBN. Pokreće se unutar transakcije iz
     * SoftDeletesFreeingSlug::delete(), pa pad brisanja knjige poništava i soft
     * delete proizvoda. forceDelete() preskačemo — FK cascade već briše knjigu.
     * Cena: restore proizvoda ne vraća knjigu.
     */
    public function deleted(Product $product): void
    {
        if ($product->isForceDeleting()) {
            return;
        }

        $product->book?->delete();
    }
}
