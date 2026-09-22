<?php

namespace App\Support;

use App\Models\Book;

/**
 * Računa i upisuje books.search_text (naslov, podnaslov, autori, izdavač).
 * Upis ide preko query buildera (update()), ne preko $book->save(), da se
 * izbegne rekurzivno okidanje Book/Product observera koji ovo i pozivaju.
 */
class BookSearchIndexer
{
    public static function compute(Book $book): string
    {
        // load(), ne loadMissing(): autori se menjaju preko pivot tabele (detach/attach),
        // pa keširana relacija na $book instanci može biti zastarela posle replaceAuthors().
        $book->load(['product', 'authors', 'publisher']);

        $parts = array_filter([
            $book->product?->name,
            $book->subtitle,
            $book->original_title,
            $book->publisher?->name,
            ...$book->authors->pluck('name')->all(),
        ], fn (?string $part) => $part !== null && $part !== '');

        return SearchText::normalize(implode(' ', $parts));
    }

    public static function sync(Book $book): void
    {
        $computed = self::compute($book);

        if ($book->getAttribute('search_text') !== $computed) {
            Book::query()->whereKey($book->getKey())->update(['search_text' => $computed]);
            $book->setAttribute('search_text', $computed);
        }
    }
}
