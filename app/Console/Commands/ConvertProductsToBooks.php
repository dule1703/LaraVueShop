<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline. Idempotentna: proizvodi koji već
 * imaju Book red se preskaču, pa je ponovno pokretanje bezbedno.
 */
class ConvertProductsToBooks extends Command
{
    protected $signature = 'catalog:convert-products-to-books
        {--category=knjige : Slug kategorije (uključuju se i njene potkategorije)}
        {--language=sr : Jezik koji se upisuje na nove Book redove}
        {--format=paperback : Format koji se upisuje na nove Book redove}
        {--dry-run : Samo prikaži šta bi bilo prebačeno, bez upisa}';

    protected $description = 'Prebacuje postojeće proizvode iz kategorije knjiga u books tabelu (idempotentno, ručno pokretanje)';

    public function handle(): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, Book::FORMATS, true)) {
            $this->error('Nevalidan --format. Dozvoljeno: '.implode(', ', Book::FORMATS));

            return self::INVALID;
        }

        $language = (string) $this->option('language');
        if (! preg_match('/^[a-z]{2,3}$/', $language)) {
            $this->error('Nevalidan --language (očekuje se ISO kod, npr. sr).');

            return self::INVALID;
        }

        $root = Category::where('slug', $this->option('category'))->first();
        if (! $root) {
            $this->warn('Kategorija "'.$this->option('category').'" ne postoji — nema šta da se prebaci.');

            return self::SUCCESS;
        }

        $products = Product::query()
            ->whereIn('category_id', $this->categoryIdsWithDescendants($root))
            ->doesntHave('book')
            ->orderBy('id')
            ->get();

        $dryRun = (bool) $this->option('dry-run');
        $converted = 0;

        foreach ($products as $product) {
            $this->line(($dryRun ? '[dry-run] ' : '').'#'.$product->id.' '.$product->name);

            if ($dryRun) {
                continue;
            }

            DB::transaction(fn () => $product->book()->create([
                'language' => $language,
                'format' => $format,
            ]));
            $converted++;
        }

        $this->info($dryRun
            ? "Dry-run: {$products->count()} proizvod(a) bi bilo prebačeno."
            : "Prebačeno: {$converted}.");

        if ($converted > 0) {
            $this->warn("Novi redovi imaju samo podrazumevani jezik ({$language}) i format ({$format}). Dopuni ISBN, autore i izdavača u adminu.");
        }

        return self::SUCCESS;
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
