<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Support\BookSearchIndexer;
use Illuminate\Console\Command;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline. Idempotentna: knjiga čiji je
 * search_text već tačan se preskače (samo se prijavi kao "bez izmene").
 * Potrebna za knjige uvezene/napravljene pre nego što je BookObserver počeo
 * da puni search_text (backfill), ali je bezbedno pokrenuti bilo kada.
 */
class ReindexSearchText extends Command
{
    protected $signature = 'catalog:reindex-search-text {--dry-run : Samo prikaži šta bi bilo izmenjeno, bez upisa}';

    protected $description = 'Ponovo izračunava books.search_text za sve knjige iz naslova/autora/izdavača (ručno pokretanje)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $total = 0;

        Book::query()->with(['product', 'authors', 'publisher'])
            ->chunkById(100, function ($books) use (&$changed, &$total, $dryRun) {
                foreach ($books as $book) {
                    $total++;
                    $before = $book->search_text;
                    $after = BookSearchIndexer::compute($book);

                    if ($before === $after) {
                        continue;
                    }

                    $changed++;
                    $title = $book->product?->name ?? "#{$book->id}";
                    $this->line(($dryRun ? '  [dry-run] ' : '  ')."{$title}: \"{$before}\" -> \"{$after}\"");

                    if (! $dryRun) {
                        Book::query()->whereKey($book->id)->update(['search_text' => $after]);
                    }
                }
            });

        $this->newLine();
        $this->info($dryRun
            ? "DRY RUN — ništa nije izmenjeno. Pregledano: {$total}, bilo bi izmenjeno: {$changed}."
            : "Pregledano: {$total}, izmenjeno: {$changed}.");

        return self::SUCCESS;
    }
}
