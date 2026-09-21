<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Publisher;
use App\Services\BookService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    public function __construct(private BookService $books)
    {
    }

    public function index(): Response
    {
        $books = Book::query()
            ->select('books.*')
            ->join('products', 'products.id', '=', 'books.product_id')
            ->with(['product:id,name,slug,price,stock,is_active', 'publisher:id,name', 'authors:id,name'])
            ->orderBy('products.name')
            ->paginate(20);

        return Inertia::render('Admin/Books/Index', ['books' => $books]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Books/Create', $this->formOptions());
    }

    public function store(BookRequest $request): RedirectResponse
    {
        $this->books->create($request->productAttributes(), $request->bookAttributes(), $request->authorRows());

        return redirect()->route('admin.books.index')->with('success', 'Knjiga je uspešno dodata.');
    }

    public function edit(Book $book): Response
    {
        $book->load(['product', 'authors']);
        $product = $book->product;

        return Inertia::render('Admin/Books/Edit', $this->formOptions() + [
            'book' => [
                'id' => $book->id,
                'category_id' => $product->category_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'image' => $product->image,
                'is_active' => $product->is_active,
                'isbn' => $book->isbn13,
                'publisher_id' => $book->publisher_id,
                'subtitle' => $book->subtitle,
                'original_title' => $book->original_title,
                'published_year' => $book->published_year,
                'pages' => $book->pages,
                'language' => $book->language,
                'script' => $book->script,
                'format' => $book->format,
                'weight_g' => $book->weight_g,
                'authors' => $book->authors
                    ->map(fn (Author $a) => ['author_id' => $a->id, 'role' => $a->pivot->role])
                    ->values(),
            ],
        ]);
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $this->books->update($book, $request->productAttributes(), $request->bookAttributes(), $request->authorRows());

        return redirect()->route('admin.books.index')->with('success', 'Knjiga je uspešno izmenjena.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        // order_items.product_id je restrict: istorijske porudžbine se ne smeju izgubiti.
        if (OrderItem::where('product_id', $book->product_id)->exists()) {
            return redirect()->route('admin.books.index')
                ->with('error', 'Knjiga se nalazi u porudžbinama i ne može se obrisati. Deaktiviraj je umesto toga.');
        }

        $this->books->delete($book);

        return redirect()->route('admin.books.index')->with('success', 'Knjiga je obrisana.');
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'publishers' => Publisher::orderBy('name')->get(['id', 'name']),
            'authors' => Author::orderBy('name')->get(['id', 'name']),
            'formats' => Book::FORMATS,
            'scripts' => Book::SCRIPTS,
            'roles' => Book::AUTHOR_ROLES,
        ];
    }
}
