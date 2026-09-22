<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use App\Services\BookCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(private BookCatalog $catalog)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->catalog->filters($request->query());

        return Inertia::render('Shop', [
            'books' => $this->catalog->paginate($filters)->through(fn (Book $book) => $this->card($book)),
            'filters' => $filters,
            'options' => $this->catalog->options(),
        ]);
    }

    public function show(string $slug): Response
    {
        $book = Book::query()
            ->select('books.*')
            ->join('products', 'products.id', '=', 'books.product_id')
            ->where('products.is_active', true)
            ->where('products.slug', $slug)
            ->with(['product.category:id,name,slug', 'publisher:id,name,slug', 'authors:id,name,slug'])
            ->firstOrFail();

        return Inertia::render('Product', ['book' => $this->detail($book)]);
    }

    /** @return array<string, mixed> */
    private function card(Book $book): array
    {
        $product = $book->product;

        return [
            'slug' => $product->slug,
            'title' => $product->name,
            'image' => $product->image,
            'price' => $product->price,
            'stock' => $product->stock,
            'available' => $product->stock === null || $product->stock > 0,
            'format' => $book->format,
            'authors' => $book->authors
                ->filter(fn (Author $a) => $a->pivot->role === 'author')
                ->pluck('name')->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Book $book): array
    {
        $product = $book->product;

        return [
            'product_id' => $product->id,
            'slug' => $product->slug,
            'title' => $product->name,
            'subtitle' => $book->subtitle,
            'original_title' => $book->original_title,
            'description' => $product->description,
            'image' => $product->image,
            'price' => $product->price,
            'stock' => $product->stock,
            'available' => $product->stock === null || $product->stock > 0,
            'category' => $product->category?->only(['name', 'slug']),
            'publisher' => $book->publisher?->only(['name', 'slug']),
            'isbn13' => $book->isbn13,
            'published_year' => $book->published_year,
            'pages' => $book->pages,
            'language' => $book->language,
            'script' => $book->script,
            'format' => $book->format,
            'weight_g' => $book->weight_g,
            'authors' => $book->authors->map(fn (Author $a) => [
                'name' => $a->name,
                'slug' => $a->slug,
                'role' => $a->pivot->role,
            ])->values(),
        ];
    }
}
