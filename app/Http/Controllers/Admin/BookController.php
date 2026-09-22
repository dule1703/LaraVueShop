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
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    public function __construct(private BookService $books, private InventoryService $inventory)
    {
    }

    public function index(Request $request): Response
    {
        $lowStock = $request->boolean('low_stock');
        $threshold = max(1, (int) $request->input('threshold', self::DEFAULT_LOW_STOCK_THRESHOLD));

        $books = Book::query()
            ->select('books.*')
            ->join('products', 'products.id', '=', 'books.product_id')
            ->with(['product:id,name,slug,price,stock,is_active', 'publisher:id,name', 'authors:id,name'])
            ->when($lowStock, function ($query) use ($threshold) {
                // stock IS NULL = neograničena zaliha (e-knjiga) — nikad "niska".
                $query->whereNotNull('products.stock')->where('products.stock', '<', $threshold);
            })
            ->when($lowStock, fn ($query) => $query->orderBy('products.stock'), fn ($query) => $query->orderBy('products.name'))
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Books/Index', [
            'books' => $books,
            'filters' => ['low_stock' => $lowStock, 'threshold' => $threshold],
        ]);
    }

    public function restock(Request $request, Book $book): RedirectResponse
    {
        $product = $book->product;

        if ($product->stock === null) {
            return redirect()->route('admin.books.index')
                ->with('error', 'Ova knjiga ima neograničenu zalihu (e-knjiga) — dopuna nije primenjiva.');
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $this->inventory->restock($product, $validated['quantity'], $request->user(), $validated['note'] ?? null);

        return redirect()->route('admin.books.index')->with('success', 'Zaliha je dopunjena.');
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
