<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Services\BookService;
use App\Support\Isbn;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ručno se pokreće, ne ulazi u deploy pipeline. Idempotentna: knjiga koja već postoji
 * (isti ISBN, ili isti naslov + autor kad ISBN nema) se preskače.
 */
class ImportBooks extends Command
{
    public const PLACEHOLDER_PRICE = 999;

    public const PLACEHOLDER_STOCK = 10;

    private const COLUMNS = [
        'title', 'subtitle', 'authors', 'publisher', 'isbn13', 'isbn10', 'published_year',
        'pages', 'language', 'script', 'format', 'category', 'description',
    ];

    protected $signature = 'catalog:import-books
        {csv : Putanja do CSV fajla}
        {--parent= : Slug kategorije pod kojom se prave nove kategorije (podrazumevano: bez roditelja)}
        {--dry-run : Samo prikaži šta bi bilo uvezeno, bez upisa}';

    protected $description = 'Uvozi knjige iz CSV-a kroz BookService (idempotentno, ručno pokretanje)';

    /** @var array{categories: array<string, string>, authors: array<string, string>, publishers: array<string, string>} */
    private array $newEntities = ['categories' => [], 'authors' => [], 'publishers' => []];

    /** @var array<string, true> */
    private array $usedSlugs = [];

    /** @var array<string, true> */
    private array $seenIsbns = [];

    /** @var array<string, bool> title+author key => has ISBN */
    private array $seenKeys = [];

    private bool $dryRun = false;

    private ?int $parentId = null;

    private int $placeholderPriceCount = 0;

    public function handle(BookService $books): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $path = (string) $this->argument('csv');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("CSV fajl ne postoji ili se ne može pročitati: {$path}");

            return self::INVALID;
        }

        if ($this->option('parent')) {
            $parent = Category::where('slug', $this->option('parent'))->first();
            if (! $parent) {
                $this->error('Kategorija sa slugom "'.$this->option('parent').'" ne postoji (--parent).');

                return self::INVALID;
            }
            $this->parentId = $parent->id;
        }

        $rows = $this->readCsv($path);
        if ($rows === null) {
            return self::INVALID;
        }

        $report = [];
        $counts = ['created' => 0, 'exists' => 0, 'error' => 0];

        foreach ($rows as $line => $raw) {
            [$status, $note] = $this->processRow($books, $raw);
            $counts[$status]++;

            $report[] = [
                $line,
                match ($status) {
                    'created' => $this->dryRun ? 'NOVA' : 'UVEZENA',
                    'exists' => 'POSTOJI',
                    'error' => 'GREŠKA',
                },
                Str::limit((string) $raw['title'], 45),
                $note,
            ];
        }

        $this->table(['Red', 'Status', 'Naslov', 'Napomena'], $report);

        $prefix = $this->dryRun ? 'Dry-run, ništa nije upisano. Bilo bi ' : '';
        $this->info($prefix.($this->dryRun ? 'uvezeno' : 'Uvezeno').": {$counts['created']}, već postoji: {$counts['exists']}, greške: {$counts['error']}.");
        foreach (['categories' => 'Kategorije', 'authors' => 'Autori', 'publishers' => 'Izdavači'] as $key => $label) {
            $this->line(sprintf('%s (%s): %d%s', $label, $this->dryRun ? 'bile bi nove' : 'nove', count($this->newEntities[$key]),
                $this->newEntities[$key] ? ' — '.implode(', ', $this->newEntities[$key]) : ''));
        }

        if ($counts['created'] > 0) {
            $this->newLine();
            $this->warn(sprintf(
                'Sve %s knjige imaju placeholder zalihu %d i su odmah aktivne (is_active = true).',
                $this->dryRun ? 'nove' : 'uvezene', self::PLACEHOLDER_STOCK,
            ));
            if ($this->placeholderPriceCount > 0) {
                $this->warn(sprintf(
                    '%d red(ova) nije imalo cenu u CSV-u i dobilo je placeholder cenu %d EUR — te knjige treba ručno doceniti kroz admin panel.',
                    $this->placeholderPriceCount, self::PLACEHOLDER_PRICE,
                ));
            }
        }

        return $counts['error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** @return array<int, array<string, ?string>>|null red u fajlu => vrednosti po koloni */
    private function readCsv(string $path): ?array
    {
        $contents = file_get_contents($path);
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $this->error('CSV nije u UTF-8 kodiranju.');

            return null;
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $contents));
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '');
        $header = is_array($header) ? array_map('trim', $header) : [];
        $missing = array_diff(self::COLUMNS, $header);
        if ($missing) {
            $this->error('CSV nema kolone: '.implode(', ', $missing));

            return null;
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null]) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $name) {
                $value = isset($cells[$i]) ? trim($cells[$i]) : '';
                $row[$name] = $value === '' ? null : $value;
            }
            $row['_columns_ok'] = count($cells) === count($header) ? '1' : null;
            $rows[$line] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0: 'created'|'exists'|'error', 1: string} */
    private function processRow(BookService $books, array $raw): array
    {
        $data = $this->validateRow($raw);
        if (is_string($data)) {
            return ['error', $data];
        }

        $duplicate = $this->findDuplicate($data);
        if ($duplicate !== null) {
            return ['exists', $duplicate];
        }

        $this->seenKeys[$data['key']] = $data['isbn13'] !== null;
        if ($data['isbn13'] !== null) {
            $this->seenIsbns[$data['isbn13']] = true;
        }

        try {
            $new = ['categories' => [], 'authors' => [], 'publishers' => []];
            $slug = $this->productSlug($data['title'], array_slice($data['authorSlugs'], 0, 1));
            $this->usedSlugs[$slug] = true;

            DB::transaction(function () use ($books, $data, $slug, &$new) {
                $category = $this->findOrCreate(Category::class, 'categories', $data['category'], $new, ['parent_id' => $this->parentId, 'is_active' => true]);
                $publisher = $data['publisher'] !== null
                    ? $this->findOrCreate(Publisher::class, 'publishers', $data['publisher'], $new)
                    : null;

                $authorRows = [];
                foreach ($data['authors'] as $entry) {
                    $author = $this->findOrCreate(Author::class, 'authors', $entry['name'], $new);
                    $authorRows[] = ['author_id' => $author?->id, 'role' => $entry['role']];
                }

                if ($this->dryRun) {
                    return;
                }

                $books->create([
                    'category_id' => $category->id,
                    'name' => $data['title'],
                    'slug' => $slug,
                    'description' => $data['description'],
                    'price' => $data['price'] ?? self::PLACEHOLDER_PRICE,
                    'stock' => self::PLACEHOLDER_STOCK,
                    'is_active' => true,
                    'image' => null,
                ], [
                    'isbn13' => $data['isbn13'],
                    'isbn10' => $data['isbn13'] !== null ? Isbn::toIsbn10($data['isbn13']) : null,
                    'publisher_id' => $publisher?->id,
                    'subtitle' => $data['subtitle'],
                    'published_year' => $data['published_year'],
                    'pages' => $data['pages'],
                    'language' => $data['language'],
                    'script' => $data['script'],
                    'format' => $data['format'],
                ], $authorRows);
            });
        } catch (Throwable $e) {
            unset($this->seenKeys[$data['key']], $this->usedSlugs[$slug ?? '']);
            if ($data['isbn13'] !== null) {
                unset($this->seenIsbns[$data['isbn13']]);
            }

            return ['error', 'Greška pri upisu: '.Str::limit($e->getMessage(), 120)];
        }

        foreach ($new as $type => $entities) {
            $this->newEntities[$type] += $entities;
        }

        if ($data['price'] === null) {
            $this->placeholderPriceCount++;
        }

        return ['created', $data['price'] === null ? 'bez cene u CSV-u, placeholder '.self::PLACEHOLDER_PRICE.' EUR' : ''];
    }

    /**
     * Vraća normalizovane podatke reda ili poruku greške.
     *
     * @return array<string, mixed>|string
     */
    private function validateRow(array $raw): array|string
    {
        $errors = [];

        if ($raw['_columns_ok'] === null) {
            return 'Pogrešan broj kolona u redu.';
        }

        $title = $raw['title'];
        if ($title === null || mb_strlen($title) > 255) {
            $errors[] = 'naslov je obavezan (max 255)';
        }
        if ($raw['subtitle'] !== null && mb_strlen($raw['subtitle']) > 255) {
            $errors[] = 'subtitle je predugačak';
        }
        if ($raw['category'] === null || mb_strlen($raw['category']) > 255) {
            $errors[] = 'kategorija je obavezna';
        }
        if ($raw['publisher'] !== null && mb_strlen($raw['publisher']) > 255) {
            $errors[] = 'izdavač je predugačak';
        }

        $isbn13 = null;
        $raw13 = Isbn::normalize($raw['isbn13']);
        $raw10 = Isbn::normalize($raw['isbn10']);
        if ($raw13 !== null) {
            if (Isbn::isValid13($raw13)) {
                $isbn13 = $raw13;
            } else {
                $errors[] = "nevalidan isbn13 ({$raw13})";
            }
        }
        if ($raw10 !== null) {
            if (! Isbn::isValid10($raw10)) {
                $errors[] = "nevalidan isbn10 ({$raw10})";
            } elseif ($raw13 === null) {
                $isbn13 = Isbn::toIsbn13($raw10);
            } elseif ($isbn13 !== null && $isbn13 !== Isbn::toIsbn13($raw10)) {
                $errors[] = 'isbn10 ne odgovara isbn13';
            }
        }

        $year = $raw['published_year'];
        if ($year !== null && (! ctype_digit($year) || $year < 1000 || $year > (int) date('Y') + 1)) {
            $errors[] = "nevalidna godina ({$year})";
        }
        $pages = $raw['pages'];
        if ($pages !== null && (! ctype_digit($pages) || $pages < 1 || $pages > 65535)) {
            $errors[] = "nevalidan broj strana ({$pages})";
        }
        if ($raw['language'] === null || ! preg_match('/^[a-z]{2,3}$/', $raw['language'])) {
            $errors[] = 'language mora biti ISO kod (npr. sr)';
        }
        if ($raw['script'] !== null && ! in_array($raw['script'], Book::SCRIPTS, true)) {
            $errors[] = "nevalidan script ({$raw['script']})";
        }
        if (! in_array($raw['format'], Book::FORMATS, true)) {
            $errors[] = 'format mora biti: '.implode('/', Book::FORMATS);
        }

        $price = null;
        if (isset($raw['price']) && $raw['price'] !== null) {
            if (! is_numeric($raw['price']) || (float) $raw['price'] < 0 || (float) $raw['price'] > 99999999.99) {
                $errors[] = "nevalidna cena ({$raw['price']})";
            } else {
                $price = round((float) $raw['price'], 2);
            }
        }

        $authors = [];
        $seenRoles = [];
        foreach (array_filter(array_map('trim', explode(';', (string) $raw['authors']))) as $entry) {
            [$name, $role] = array_pad(array_map('trim', explode(':', $entry, 2)), 2, 'author');
            $role = $role === '' ? 'author' : $role;
            $authorSlug = Str::slug($name);

            if ($name === '' || $authorSlug === '') {
                $errors[] = "nevalidan autor ({$entry})";
            } elseif (! in_array($role, Book::AUTHOR_ROLES, true)) {
                $errors[] = "nepoznata uloga ({$role})";
            } elseif (isset($seenRoles["{$authorSlug}:{$role}"])) {
                $errors[] = "duplirana uloga ({$entry})";
            } else {
                $seenRoles["{$authorSlug}:{$role}"] = true;
                $authors[] = ['slug' => $authorSlug, 'name' => $name, 'role' => $role];
            }
        }
        if (count($authors) > 20) {
            $errors[] = 'previše autora (max 20)';
        }

        if ($errors) {
            return implode('; ', $errors);
        }

        $authorSlugs = array_values(array_unique(array_column($authors, 'slug')));
        $slugs = $authorSlugs;
        sort($slugs);

        return [
            'title' => $title,
            'subtitle' => $raw['subtitle'],
            'authors' => $authors,
            'authorSlugs' => $authorSlugs,
            'publisher' => $raw['publisher'],
            'isbn13' => $isbn13,
            'published_year' => $year === null ? null : (int) $year,
            'pages' => $pages === null ? null : (int) $pages,
            'language' => $raw['language'],
            'script' => $raw['script'],
            'format' => $raw['format'],
            'category' => $raw['category'],
            'description' => $raw['description'],
            'price' => $price,
            'key' => Str::lower($title).'|'.implode(',', $slugs),
        ];
    }

    /**
     * ISBN se poredi sa ISBN-om; naslov + autor služi kad ISBN nedostaje na jednoj od strana
     * (dva izdanja sa različitim ISBN-om su različite knjige).
     */
    private function findDuplicate(array $data): ?string
    {
        $isbn = $data['isbn13'];

        if ($isbn !== null && (isset($this->seenIsbns[$isbn]) || Book::where('isbn13', $isbn)->exists())) {
            return "isti ISBN ({$isbn})";
        }

        $seen = $this->seenKeys[$data['key']] ?? null;
        if ($seen !== null && ($isbn === null || $seen === false)) {
            return 'isti naslov i autor (ranije u fajlu)';
        }

        $authorSlugs = $data['authorSlugs'];
        $exists = Product::query()
            ->where('name', $data['title'])
            ->whereHas('book', function ($book) use ($isbn, $authorSlugs) {
                if ($isbn !== null) {
                    $book->whereNull('isbn13');
                }
                $authorSlugs
                    ? $book->whereHas('authors', fn ($a) => $a->whereIn('authors.slug', $authorSlugs))
                    : $book->whereDoesntHave('authors');
            })
            ->exists();

        return $exists ? 'isti naslov i autor' : null;
    }

    /**
     * Postojeći red se nikad ne menja; u dry-run-u se ništa ne upisuje, samo se beleži šta bi bilo novo.
     *
     * @param  class-string<Model>  $class
     * @param  array<string, array<string, string>>  $new
     */
    private function findOrCreate(string $class, string $type, string $name, array &$new, array $extra = []): ?Model
    {
        $slug = Str::slug($name);

        $existing = $class::where('slug', $slug)->first();
        if ($existing) {
            return $existing;
        }

        $new[$type][$slug] = $name;
        if ($this->dryRun) {
            return null;
        }

        return $class::create(['name' => $name, 'slug' => $slug] + $extra);
    }

    /** @param  list<string>  $authorSlugs */
    private function productSlug(string $title, array $authorSlugs): string
    {
        $base = substr(Str::slug($title), 0, 200) ?: 'knjiga';
        $candidates = [$base];
        if ($authorSlugs) {
            $candidates[] = $base.'-'.$authorSlugs[0];
        }

        foreach ($candidates as $candidate) {
            if ($this->slugFree($candidate)) {
                return $candidate;
            }
        }

        for ($i = 2;; $i++) {
            if ($this->slugFree("{$base}-{$i}")) {
                return "{$base}-{$i}";
            }
        }
    }

    private function slugFree(string $slug): bool
    {
        return ! isset($this->usedSlugs[$slug]) && ! Product::where('slug', $slug)->exists();
    }
}
