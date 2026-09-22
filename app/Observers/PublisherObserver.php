<?php

namespace App\Observers;

use App\Models\Publisher;

/**
 * Isti razlog i isto upozorenje o select()-u kao AuthorObserver: naziv
 * izdavača ulazi u books.search_text.
 */
class PublisherObserver
{
    public function saved(Publisher $publisher): void
    {
        $publisher->books()->chunkById(100, function ($books) {
            foreach ($books as $book) {
                $book->touch();
            }
        });
    }
}
