<?php

namespace App\Observers;

use App\Models\Author;

/**
 * Ime/slug autora ulazi u books.search_text (vidi BookSearchIndexer), pa
 * izmena autora mora da osveži search_text svih njegovih knjiga. touch()
 * po knjizi okida postojeći BookObserver -> BookSearchIndexer mehanizam
 * (ne duplira logiku računanja ovde). chunkById da se knjige jednog
 * (izuzetno plodnog) autora ne učitaju sve odjednom u memoriju — pun red
 * (ne select('id')): BookSearchIndexer čita product_id/publisher_id/
 * subtitle/original_title direktno sa Book instance, pa bi ograničen
 * select ostavio te kolone NULL i obrisao ih iz search_text-a.
 */
class AuthorObserver
{
    public function saved(Author $author): void
    {
        $author->books()->chunkById(100, function ($books) {
            foreach ($books as $book) {
                $book->touch();
            }
        });
    }
}
