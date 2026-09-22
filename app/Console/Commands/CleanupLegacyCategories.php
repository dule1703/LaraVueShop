<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline. Idempotentna: proizvodi bez
 * order_items/stock_movements se brišu, ostali se samo deaktiviraju (FK
 * restrict), pa je ponovno pokretanje bezbedno — drugi put ih više nema ili
 * su već neaktivni.
 */
class CleanupLegacyCategories extends Command
{
    private const DEFAULT_CATEGORIES = ['clothes-and-shoes', 'electronics', 'home-appliances'];

    protected $signature = 'catalog:cleanup-legacy-categories
        {--category=* : Slug kategorije za čišćenje (ponovi opciju za više). Podrazumevano: clothes-and-shoes, electronics, home-appliances}
        {--dry-run : Samo prikaži šta bi bilo obrisano/deaktivirano, bez upisa}';

    protected $description = 'Briše stare ne-knjižne kategorije i njihove proizvode; proizvode sa order_items/stock_movements samo deaktivira (ručno pokretanje)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $slugs = $this->option('category') ?: self::DEFAULT_CATEGORIES;

        $totalDeleted = 0;
        $totalDeactivated = 0;
        $categoriesDeleted = 0;

        foreach ($slugs as $slug) {
            $category = Category::where('slug', $slug)->first();
            if (! $category) {
                $this->warn("Kategorija \"{$slug}\" ne postoji — preskačem.");

                continue;
            }

            $this->info("== {$category->name} (slug: {$category->slug}) ==");

            $categoryIds = $this->categoryIdsWithDescendants($category);
            $products = Product::whereIn('category_id', $categoryIds)->orderBy('id')->get();

            if ($products->isEmpty()) {
                $this->line('  Nema proizvoda.');
            }

            [$toDelete, $toDeactivate] = $products->partition(
                fn (Product $product) => ! $this->hasHistory($product)
            );

            foreach ($toDelete as $product) {
                $this->line(($dryRun ? '  [dry-run] ' : '  ')."OBRISATI: #{$product->id} {$product->name}");
            }

            foreach ($toDeactivate as $product) {
                $status = $product->is_active ? 'DEAKTIVIRATI' : 'već neaktivan';
                $this->line(($dryRun ? '  [dry-run] ' : '  ')."{$status} (ima porudžbine/zalihe): #{$product->id} {$product->name}");
            }

            $newlyDeactivated = $toDeactivate->filter(fn (Product $product) => $product->is_active)->count();

            if ($dryRun) {
                $totalDeleted += $toDelete->count();
                $totalDeactivated += $newlyDeactivated;

                continue;
            }

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

            $totalDeleted += $toDelete->count();
            $totalDeactivated += $newlyDeactivated;

            $remaining = Product::where('category_id', $category->id)->count();
            if ($remaining > 0) {
                $this->warn("  Kategorija NIJE obrisana — još uvek ima {$remaining} proizvod(a) sa istorijom porudžbina/zaliha.");

                continue;
            }

            try {
                $category->delete();
                $categoriesDeleted++;
                $this->info('  Kategorija obrisana.');
            } catch (QueryException $e) {
                $this->warn("  Kategorija NIJE obrisana (FK ograničenje): {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "DRY RUN — ništa nije izmenjeno. Obrisalo bi se: {$totalDeleted} proizvod(a), deaktiviralo: {$totalDeactivated} proizvod(a)."
            : "Obrisano: {$totalDeleted} proizvod(a). Deaktivirano: {$totalDeactivated} proizvod(a). Obrisano kategorija: {$categoriesDeleted}.");

        return self::SUCCESS;
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
