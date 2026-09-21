<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Product + Book + autori se uvek upisuju zajedno u jednoj transakciji.
 */
class BookService
{
    /**
     * @param  list<array{author_id: int, role: string}>  $authors
     */
    public function create(array $product, array $book, array $authors): Book
    {
        return DB::transaction(function () use ($product, $book, $authors) {
            $productModel = Product::create($product);
            $bookModel = $productModel->book()->create($book);

            $this->replaceAuthors($bookModel, $authors);

            return $bookModel;
        });
    }

    /**
     * @param  list<array{author_id: int, role: string}>  $authors
     */
    public function update(Book $book, array $product, array $bookData, array $authors): Book
    {
        return DB::transaction(function () use ($book, $product, $bookData, $authors) {
            $book->product->update($product);
            $book->update($bookData);

            $this->replaceAuthors($book, $authors);

            return $book;
        });
    }

    /**
     * Brisanje proizvoda kaskadno briše knjigu i author_book redove.
     */
    public function delete(Book $book): void
    {
        DB::transaction(fn () => $book->product->delete());
    }

    /**
     * detach() + attach(), nikad sync(): PK author_book je (book_id, author_id, role),
     * a sync() poredi samo po author_id pa ne može da predstavi istog autora sa dve uloge.
     * attach() se zove po jedan red jer attach([id => attrs]) ne dozvoljava dupli ključ.
     *
     * @param  list<array{author_id: int, role: string}>  $authors
     */
    private function replaceAuthors(Book $book, array $authors): void
    {
        $book->authors()->detach();

        foreach (array_values($authors) as $position => $row) {
            $book->authors()->attach($row['author_id'], [
                'role' => $row['role'],
                'position' => $position,
            ]);
        }
    }
}
