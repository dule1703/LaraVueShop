<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline.
 *
 * Soft-briše sve proizvode ciljanih kategorija (i potkategorija), pa same
 * kategorije (od najdublje ka korenu). Istorija porudžbina/zaliha ostaje
 * čitljiva: order_items čuvaju snapshot, a relacije ka proizvodu su
 * withTrashed() (vidi CLAUDE.md, "soft delete"). Pre izvršenja ispisuje plan sa
 * brojem pogođenih proizvoda/order_items/stock_movements/Book redova i traži
 * potvrdu. Idempotentna: soft-obrisana kategorija više ne postoji za global
 * scope, pa se pri ponovnom pokretanju preskače.
 *
 * Soft delete proizvoda TVRDO briše povezani Book red (ProductObserver) — ako
 * plan sadrži bar jedan, komanda staje dok se ne doda --allow-book-delete
 * (--force to NE zamenjuje).
 */
class CleanupLegacyCategories extends Command
{
    private const DEFAULT_CATEGORIES = ['clothes-and-shoes', 'electronics', 'home-appliances'];

    protected $signature = 'catalog:cleanup-legacy-categories
        {--category=* : Slug kategorije za čišćenje (ponovi opciju za više). Podrazumevano: clothes-and-shoes, electronics, home-appliances ("books" samo eksplicitno)}
        {--dry-run : Samo ispiši plan, bez pitanja i bez upisa}
        {--force : Preskoči pitanje za potvrdu (NE preskače --allow-book-delete)}
        {--allow-book-delete : Dozvoli da plan tvrdo obriše Book redove proizvoda koji se brišu}';

    protected $description = 'Soft-briše stare ne-knjižne kategorije i njihove proizvode uz ispis plana i potvrdu (ručno pokretanje)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slugs = $this->option('category') ?: self::DEFAULT_CATEGORIES;

        $plans = $this->buildPlans($slugs);

        if ($plans === []) {
            $this->info('Nema šta da se očisti.');

            return self::SUCCESS;
        }

        $totals = $this->printPlan($plans, in_array('books', $slugs, true));

        if ($dryRun) {
            $this->info('DRY RUN — ništa nije izmenjeno.');
            if ($totals['books'] > 0) {
                $this->warn('Pravo pokretanje bi tražilo --allow-book-delete (plan tvrdo briše Book redove).');
            }

            return self::SUCCESS;
        }

        if ($totals['books'] > 0 && ! $this->option('allow-book-delete')) {
            $this->error("Plan tvrdo briše {$totals['books']} Book red(ova) (soft delete proizvoda briše knjigu). "
                .'Ništa nije izmenjeno. Ponovo pokreni sa --allow-book-delete (--force to ne zamenjuje).');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Nastaviti sa soft brisanjem iz plana?', false)) {
            $this->warn('Prekinuto — ništa nije izmenjeno.');

            return self::SUCCESS;
        }

        $deletedProducts = 0;
        $deletedCategories = 0;

        foreach ($plans as $plan) {
            [$products, $categories] = DB::transaction(fn () => $this->softDeleteTree($plan['category_ids']));
            $deletedProducts += $products;
            $deletedCategories += $categories;
        }

        $this->info("Soft-obrisano: {$deletedProducts} proizvod(a), {$deletedCategories} kategorija.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array{root: Category, category_ids: list<int>, products: int, order_items: int, orders: int, stock_movements: int, books: int}>
     */
    private function buildPlans(array $slugs): array
    {
        $plans = [];
        $covered = [];

        foreach ($slugs as $slug) {
            $root = Category::where('slug', $slug)->first();

            if (! $root) {
                $this->warn("Kategorija \"{$slug}\" ne postoji — preskačem.");

                continue;
            }

            if (in_array($root->id, $covered, true)) {
                $this->line("Kategorija \"{$slug}\" je već obuhvaćena drugom metom — preskačem.");

                continue;
            }

            $categoryIds = $this->categoryIdsWithDescendants($root);
            $covered = array_merge($covered, $categoryIds);

            $productIds = Product::whereIn('category_id', $categoryIds)->pluck('id');
            $orderItems = DB::table('order_items')->whereIn('product_id', $productIds);

            $plans[] = [
                'root' => $root,
                'category_ids' => $categoryIds,
                'products' => $productIds->count(),
                'order_items' => (clone $orderItems)->count(),
                'orders' => (clone $orderItems)->distinct()->count('order_id'),
                'stock_movements' => DB::table('stock_movements')->whereIn('product_id', $productIds)->count(),
                'books' => Book::whereIn('product_id', $productIds)->count(),
            ];
        }

        return $plans;
    }

    /**
     * @param  list<array<string, mixed>>  $plans
     * @return array{categories: int, products: int, order_items: int, orders: int, stock_movements: int, books: int}
     */
    private function printPlan(array $plans, bool $targetsBooks): array
    {
        $totals = ['categories' => 0, 'products' => 0, 'order_items' => 0, 'orders' => 0, 'stock_movements' => 0, 'books' => 0];

        $this->newLine();
        $this->info('PLAN:');

        foreach ($plans as $plan) {
            $root = $plan['root'];
            $descendants = count($plan['category_ids']) - 1;

            $this->line("== {$root->name} (slug: {$root->slug}) ==");
            $this->line("  Potkategorija: {$descendants}");
            $this->line("  Proizvoda za soft delete: {$plan['products']}");
            $this->line("  Pogođenih order_items: {$plan['order_items']} (porudžbina: {$plan['orders']})");
            $this->line("  stock_movements: {$plan['stock_movements']}");
            $this->line("  Book redova za TVRDO brisanje: {$plan['books']}");

            $totals['categories'] += count($plan['category_ids']);
            foreach (['products', 'order_items', 'orders', 'stock_movements', 'books'] as $key) {
                $totals[$key] += $plan[$key];
            }
        }

        $this->newLine();
        $this->line("UKUPNO: kategorija {$totals['categories']}, proizvoda {$totals['products']}, "
            ."order_items {$totals['order_items']} (porudžbina {$totals['orders']}), "
            ."stock_movements {$totals['stock_movements']}, Book redova za tvrdo brisanje {$totals['books']}");

        if ($targetsBooks) {
            $this->warn('UPOZORENJE: meta uključuje kategoriju "books" — proizvodi sa porudžbinama će biti soft-obrisani (istorija ostaje čitljiva preko snapshot-a).');
        }

        $this->newLine();

        return $totals;
    }

    /**
     * @param  list<int>  $categoryIds  koren prvi, potomci posle (BFS)
     * @return array{0: int, 1: int} [soft-obrisano proizvoda, soft-obrisano kategorija]
     */
    private function softDeleteTree(array $categoryIds): array
    {
        $products = 0;
        $categories = 0;

        // delete() preko modela (ne query builder-a): okida ProductObserver (Book) i preimenovanje slug-a.
        Product::whereIn('category_id', $categoryIds)->orderBy('id')->chunkById(100, function ($chunk) use (&$products) {
            foreach ($chunk as $product) {
                $product->delete();
                $products++;
            }
        });

        // Od najdublje ka korenu — soft delete ne okida FK nullOnDelete za parent_id.
        foreach (array_reverse($categoryIds) as $categoryId) {
            $category = Category::find($categoryId);

            if ($category) {
                $category->delete();
                $categories++;
            }
        }

        return [$products, $categories];
    }

    /** @return list<int> */
    private function categoryIdsWithDescendants(Category $root): array
    {
        $ids = [$root->id];
        $frontier = [$root->id];

        while ($frontier) {
            $frontier = Category::whereIn('parent_id', $frontier)->pluck('id')->diff($ids)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
