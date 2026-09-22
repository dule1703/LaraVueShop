<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline. Idempotentna: proizvodi bez
 * order_items/stock_movements se brišu, ostali se samo deaktiviraju (FK
 * restrict), pa je ponovno pokretanje bezbedno — drugi put ih više nema ili
 * su već neaktivni. Isto važi za kategorije: kategorija sa preostalim
 * (deaktiviranim) proizvodima se ne briše, ali se deaktivira — BookCatalog
 * filter dropdown prikazuje samo `is_active` kategorije (vidi
 * BookCatalog::categoryOptions), pa bi inače ostala vidljiva i posle
 * čišćenja proizvoda.
 */
class CleanupLegacyCategories extends Command
{
    private const DEFAULT_CATEGORIES = ['clothes-and-shoes', 'electronics', 'home-appliances'];

    protected $signature = 'catalog:cleanup-legacy-categories
        {--category=* : Slug kategorije za čišćenje (ponovi opciju za više). Podrazumevano: clothes-and-shoes, electronics, home-appliances}
        {--dry-run : Samo prikaži šta bi bilo obrisano/deaktivirano, bez upisa}';

    protected $description = 'Briše stare ne-knjižne kategorije i njihove proizvode; proizvode i kategorije sa order_items/stock_movements samo deaktivira (ručno pokretanje)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slugs = $this->option('category') ?: self::DEFAULT_CATEGORIES;

        $totalProductsDeleted = 0;
        $totalProductsDeactivated = 0;
        $totalCategoriesDeleted = 0;
        $totalCategoriesDeactivated = 0;

        foreach ($slugs as $slug) {
            $root = Category::where('slug', $slug)->first();
            if (! $root) {
                $this->warn("Kategorija \"{$slug}\" ne postoji — preskačem.");

                continue;
            }

            $this->info("== {$root->name} (slug: {$root->slug}) ==");

            $categoryIds = $this->categoryIdsWithDescendants($root);
            $products = Product::whereIn('category_id', $categoryIds)->orderBy('id')->get();

            if ($products->isEmpty()) {
                $this->line('  Nema proizvoda.');
            }

            [$toDelete, $toDeactivate] = $products->partition(
                fn (Product $product) => ! $this->hasHistory($product)
            );

            foreach ($toDelete as $product) {
                $this->line(($dryRun ? '  [dry-run] ' : '  ')."OBRISATI proizvod: #{$product->id} {$product->name}");
            }

            foreach ($toDeactivate as $product) {
                $status = $product->is_active ? 'DEAKTIVIRATI' : 'već neaktivan';
                $this->line(($dryRun ? '  [dry-run] ' : '  ')."{$status} proizvod (ima porudžbine/zalihe): #{$product->id} {$product->name}");
            }

            $newlyDeactivated = $toDeactivate->filter(fn (Product $product) => $product->is_active)->count();
            $totalProductsDeleted += $toDelete->count();
            $totalProductsDeactivated += $newlyDeactivated;

            if (! $dryRun) {
                DB::transaction(function () use ($toDelete, $toDeactivate) {
                    foreach ($toDeactivate as $product) {
                        if ($product->is_active) {
                            $product->is_active = false;
                            $product->save();
                        }
                    }

                    foreach ($toDelete as $product) {
                        $product->delete();
                    }
                });
            }

            // Preostali proizvodi po kategoriji (direktno; category_id se ne nasleđuje niz stablo).
            $remainingByCategory = $toDeactivate->groupBy('category_id')->map->count();

            foreach (array_reverse($categoryIds) as $categoryId) {
                [$deleted, $deactivated] = $this->resolveCategory($categoryId, $remainingByCategory, $dryRun);
                $totalCategoriesDeleted += $deleted;
                $totalCategoriesDeactivated += $deactivated;
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "DRY RUN — ništa nije izmenjeno. Obrisalo bi se: {$totalProductsDeleted} proizvod(a) i {$totalCategoriesDeleted} kategorija; deaktiviralo: {$totalProductsDeactivated} proizvod(a) i {$totalCategoriesDeactivated} kategorija."
            : "Obrisano: {$totalProductsDeleted} proizvod(a), {$totalCategoriesDeleted} kategorija. Deaktivirano: {$totalProductsDeactivated} proizvod(a), {$totalCategoriesDeactivated} kategorija.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $remainingByCategory  broj proizvoda sa istorijom po category_id (iz ovog pokretanja)
     * @return array{0: int, 1: int} [obrisano kategorija, deaktivirano kategorija] (0 ili 1 svaki)
     */
    private function resolveCategory(int $categoryId, Collection $remainingByCategory, bool $dryRun): array
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return [0, 0]; // već obrisana u prethodnom pokretanju
        }

        $remaining = $dryRun
            ? $remainingByCategory->get($categoryId, 0)
            : Product::where('category_id', $categoryId)->count();

        if ($remaining === 0) {
            if ($dryRun) {
                $this->line("  [dry-run] OBRISATI kategoriju: {$category->name}");

                return [1, 0];
            }

            try {
                $category->delete();
                $this->info("  Kategorija obrisana: {$category->name}");

                return [1, 0];
            } catch (QueryException $e) {
                $this->warn("  Kategorija NIJE obrisana (FK ograničenje): {$category->name}");

                return [0, 0];
            }
        }

        if (! $category->is_active) {
            $this->line("  Kategorija već neaktivna: {$category->name} ({$remaining} proizvod(a) sa istorijom)");

            return [0, 0];
        }

        $action = $dryRun ? 'DEAKTIVIRATI kategoriju' : 'Kategorija deaktivirana';
        $this->line(($dryRun ? '  [dry-run] ' : '  ')."{$action}: {$category->name} — i dalje ima {$remaining} proizvod(a) sa istorijom porudžbina/zaliha (ne prikazuje se u filterima kataloga).");

        if (! $dryRun) {
            $category->is_active = false;
            $category->save();
        }

        return [0, 1];
    }

    private function hasHistory(Product $product): bool
    {
        return DB::table('order_items')->where('product_id', $product->id)->exists()
            || DB::table('stock_movements')->where('product_id', $product->id)->exists();
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
