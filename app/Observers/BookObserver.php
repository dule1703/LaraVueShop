<?php

namespace App\Observers;

use App\Models\Book;
use App\Support\BookSearchIndexer;

class BookObserver
{
    public function saved(Book $book): void
    {
        BookSearchIndexer::sync($book);
    }
}
