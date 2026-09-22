<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Support\SearchText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Javni katalog: samo aktivne knjige, filtrirane i uvek paginirane.
 */
class BookCatalog
{
    public const PER_PAGE = 12;

    public const FILTER_KEYS = [
        'category', 'author', 'publisher', 'language', 'script', 'format', 'price_min', 'price_max', 'in_stock', 'search',
    ];

    /**
     * Zadržava samo ispravne vrednosti iz query string-a; neispravne (npr. ?script=xyz,
     * ?price_min=abc, ?author[]=a) se tiho ignorišu umesto da bace 422 na javnoj stranici.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|bool|null>
     */
    public function filters(array $input): array
    {
        $valid = Validator::make($input, [
            'category' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2,3}$/'],
            'script' => ['nullable', Rule::in(Book::SCRIPTS)],
            'format' => ['nullable', Rule::in(Book::FORMATS)],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'in_stock' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
        ])->valid();

        $filters = [];
        foreach (self::FILTER_KEYS as $key) {
            $value = $valid[$key] ?? null;
            $filters[$key] = ($value === '' || $value === null) ? null : $value;
        }
        $filters['in_stock'] = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN);

        return $filters;
    }

    /**
     * @param  array<string, string|bool|null>  $filters  rezultat filters()
     */
    public function query(array $filters): Builder
    {
        $query = Book::query()
            ->select('books.*')
            ->join('products', 'products.id', '=', 'books.product_id')
            ->where('products.is_active', true)
            ->with([
                'product:id,category_id,name,slug,price,stock,image',
                'authors:id,name,slug',
            ])
            ->orderBy('products.name')
            ->orderBy('books.id');

        if ($filters['category'] !== null) {
            $query->whereIn('products.category_id', $this->categoryIdsWithDescendants($filters['category']));
        }

        if ($filters['author'] !== null) {
            $query->whereHas('authors', fn (Builder $q) => $q->where('authors.slug', $filters['author']));
        }

        if ($filters['publisher'] !== null) {
            $query->whereIn('books.publisher_id', Publisher::query()->where('slug', $filters['publisher'])->select('id'));
        }

        foreach (['language', 'script', 'format'] as $column) {
            if ($filters[$column] !== null) {
                $query->where("books.{$column}", $filters[$column]);
            }
        }

        if ($filters['price_min'] !== null) {
            $query->where('products.price', '>=', $filters['price_min']);
        }

        if ($filters['price_max'] !== null) {
            $query->where('products.price', '<=', $filters['price_max']);
        }

        // NULL zaliha = neograničeno (e-knjiga) i računa se kao "na stanju".
        if ($filters['in_stock']) {
            $query->where(fn (Builder $q) => $q->where('products.stock', '>', 0)->orWhereNull('products.stock'));
        }

        if ($filters['search'] !== null) {
            $normalized = SearchText::normalize($filters['search']);
            if ($normalized !== '') {
                // Prost LIKE nad normalizovanom search_text kolonom — whereFullText()
                // se namerno ne koristi (ne radi na SQLite, ponaša se drugačije na MariaDB).
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalized);
                $query->where('books.search_text', 'like', '%'.$escaped.'%');
            }
        }

        return $query;
    }

    /**
     * @param  array<string, string|bool|null>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Opcije za filtere — samo ono što stvarno postoji u aktivnim knjigama.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $activeBook = fn (Builder $q) => $q->whereHas('product', fn (Builder $p) => $p->where('is_active', true));

        return [
            'categories' => $this->categoryOptions(),
            'authors' => Author::query()->whereHas('books', $activeBook)->orderBy('name')->get(['id', 'name', 'slug']),
            'publishers' => Publisher::query()->whereHas('books', $activeBook)->orderBy('name')->get(['id', 'name', 'slug']),
            'languages' => Book::query()
                ->whereHas('product', fn (Builder $p) => $p->where('is_active', true))
                ->distinct()->orderBy('language')->pluck('language'),
            'scripts' => Book::SCRIPTS,
            'formats' => Book::FORMATS,
        ];
    }

    /**
     * Aktivne kategorije kao spisak po redosledu stabla (roditelj pa deca) sa dubinom uvlačenja.
     *
     * @return list<array{id: int, name: string, slug: string, depth: int}>
     */
    private function categoryOptions(): array
    {
        $byParent = Category::query()
            ->where('is_active', true)
            ->orderBy('position')->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'position'])
            ->groupBy(fn (Category $c) => $c->parent_id ?? 0);

        $flat = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$flat, $byParent) {
            foreach ($byParent->get($parentId, []) as $category) {
                $flat[] = ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'depth' => $depth];
                $walk($category->id, $depth + 1);
            }
        };
        $walk(0, 0);

        // Aktivna podkategorija čiji je roditelj neaktivan ne dolazi do korena — ne prikazuje se.
        return $flat;
    }

    /**
     * Kategorija + sve njene aktivne potkategorije. Nepoznat ili neaktivan slug → prazna lista
     * (nula rezultata), ne "ignoriši filter".
     *
     * @return list<int>
     */
    private function categoryIdsWithDescendants(string $slug): array
    {
        $categories = Category::query()->where('is_active', true)->get(['id', 'parent_id', 'slug']);
        $root = $categories->firstWhere('slug', $slug);

        if ($root === null) {
            return [];
        }

        $ids = [$root->id];
        $queue = [$root->id];
        while ($queue !== []) {
            $parent = array_shift($queue);
            foreach ($categories->where('parent_id', $parent) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }
}
