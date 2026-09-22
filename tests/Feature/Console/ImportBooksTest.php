<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ImportBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportBooksTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'title,subtitle,authors,publisher,isbn13,isbn10,published_year,pages,language,script,format,category,description';

    private const HEADER_WITH_PRICE = self::HEADER.',price';

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->csv = tempnam(sys_get_temp_dir(), 'import_');
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);

        parent::tearDown();
    }

    /** @param  list<string>  $lines */
    private function writeCsv(array $lines, string $header = self::HEADER): string
    {
        file_put_contents($this->csv, implode("\n", [$header, ...$lines])."\n");

        return $this->csv;
    }

    private function fullRow(): string
    {
        return 'Na Drini ćuprija,podnaslov,Ivo Andrić:author,Vulkan izdavaštvo,9788610034745,,2024,318,sr,Latn,paperback,Klasici,"Opis, sa zarezom"';
    }

    public function test_uvozi_knjigu_sa_autorom_izdavacem_i_kategorijom(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([$this->fullRow()])])->assertSuccessful();

        $book = Book::with(['product', 'authors', 'publisher'])->sole();
        $this->assertSame('Na Drini ćuprija', $book->product->name);
        $this->assertSame('na-drini-cuprija', $book->product->slug);
        $this->assertSame('Opis, sa zarezom', $book->product->description);
        $this->assertSame('9788610034745', $book->isbn13);
        $this->assertSame('8610034747', $book->isbn10);
        $this->assertSame('Vulkan izdavaštvo', $book->publisher->name);
        $this->assertSame(['Ivo Andrić'], $book->authors->pluck('name')->all());
        $this->assertSame('author', $book->authors->first()->pivot->role);
        $this->assertSame(2024, $book->published_year);
        $this->assertSame(318, $book->pages);
        $this->assertSame('Klasici', $book->product->category->name);
    }

    public function test_bez_price_kolone_koristi_placeholder_cenu_i_knjiga_je_aktivna(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([$this->fullRow()])])
            ->expectsOutputToContain('placeholder cenu')
            ->assertSuccessful();

        $product = Product::sole();
        $this->assertEquals(ImportBooks::PLACEHOLDER_PRICE, $product->price);
        $this->assertSame(ImportBooks::PLACEHOLDER_STOCK, $product->stock);
        $this->assertTrue($product->is_active);
    }

    public function test_koristi_cenu_iz_csv_kada_kolona_postoji(): void
    {
        $this->artisan('catalog:import-books', [
            'csv' => $this->writeCsv([$this->fullRow().',16.99'], self::HEADER_WITH_PRICE),
        ])->assertSuccessful();

        $product = Product::sole();
        $this->assertEquals(16.99, $product->price);
        $this->assertTrue($product->is_active);
    }

    public function test_red_bez_cene_u_price_koloni_dobija_placeholder_i_ostali_se_ne_diraju(): void
    {
        $this->artisan('catalog:import-books', [
            'csv' => $this->writeCsv([$this->fullRow().','], self::HEADER_WITH_PRICE),
        ])->expectsOutputToContain('1 red(ova) nije imalo cenu')->assertSuccessful();

        $this->assertEquals(ImportBooks::PLACEHOLDER_PRICE, Product::sole()->price);
    }

    public function test_nevalidna_cena_je_greska_i_red_se_preskace(): void
    {
        $this->artisan('catalog:import-books', [
            'csv' => $this->writeCsv(['Loša cena,,Ivo Andrić:author,,,,,,sr,,paperback,Klasici,,abc'], self::HEADER_WITH_PRICE),
        ])->assertFailed();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_prazna_polja_ostaju_null(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Prokleta avlija,,Ivo Andrić:author,,,,,,sr,,paperback,Klasici,',
        ])])->assertSuccessful();

        $book = Book::with('product')->sole();
        $this->assertNull($book->isbn13);
        $this->assertNull($book->isbn10);
        $this->assertNull($book->subtitle);
        $this->assertNull($book->publisher_id);
        $this->assertNull($book->published_year);
        $this->assertNull($book->pages);
        $this->assertNull($book->script);
        $this->assertNull($book->product->description);
        $this->assertDatabaseCount('publishers', 0);
    }

    public function test_vise_autora_sa_ulogama_ukljucujuci_isti_autor_sa_dve_uloge(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Vidovite priče,,Ljubivoje Ršumović:author;Dušan Petričić:illustrator;Ljubivoje Ršumović:editor,Laguna,,,2013,,sr,,paperback,Deca,',
        ])])->assertSuccessful();

        $book = Book::sole();
        $this->assertSame(
            [['Ljubivoje Ršumović', 'author'], ['Dušan Petričić', 'illustrator'], ['Ljubivoje Ršumović', 'editor']],
            $book->authors->map(fn ($a) => [$a->name, $a->pivot->role])->all(),
        );
        $this->assertDatabaseCount('authors', 2);
    }

    public function test_dupli_import_sa_isbn_ne_pravi_duplikate(): void
    {
        $path = $this->writeCsv([$this->fullRow()]);

        $this->artisan('catalog:import-books', ['csv' => $path])->assertSuccessful();
        $this->artisan('catalog:import-books', ['csv' => $path])->assertSuccessful();

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('books', 1);
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseCount('publishers', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('author_book', 1);
    }

    public function test_dupli_import_bez_isbn_proverava_naslov_i_autora(): void
    {
        $path = $this->writeCsv([
            'Prokleta avlija,,Ivo Andrić:author,,,,1954,,sr,,paperback,Klasici,',
            'Gospođa ministarka,,Branislav Nušić:author,,,,1929,,sr,,paperback,Drama,',
        ]);

        $this->artisan('catalog:import-books', ['csv' => $path])->assertSuccessful();
        $this->artisan('catalog:import-books', ['csv' => $path])->assertSuccessful();

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('books', 2);
        $this->assertDatabaseCount('author_book', 2);
    }

    public function test_duplikati_unutar_istog_fajla_se_preskacu(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            $this->fullRow(),
            $this->fullRow(),
            'Kora,,Vasko Popa:author,,,,,,sr,,paperback,Poezija,',
            'Kora,,Vasko Popa:author,,,,,,sr,,paperback,Poezija,',
        ])])->assertSuccessful();

        $this->assertDatabaseCount('books', 2);
    }

    public function test_isti_naslov_drugog_autora_i_drugog_isbn_izdanja_su_razlicite_knjige(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Koreni,,Dobrica Ćosić:author,,9788652120789,,,,sr,Cyrl,paperback,Roman,',
            'Koreni,,Alex Haley:author,,,,,,en,Latn,paperback,Roman,',
            'Koreni,,Dobrica Ćosić:author,,9788610034745,,,,sr,Cyrl,hardcover,Roman,',
        ])])->assertSuccessful();

        $this->assertDatabaseCount('books', 3);
        $this->assertSame(3, Product::distinct('slug')->count('slug'));
    }

    public function test_ne_duplira_knjigu_koju_je_admin_vec_uneo_bez_isbn(): void
    {
        $product = Product::factory()->create(['name' => 'Koreni']);
        $author = Author::create(['name' => 'Dobrica Ćosić', 'slug' => 'dobrica-cosic']);
        $product->book()->create(['language' => 'sr', 'format' => 'paperback'])
            ->authors()->attach($author->id, ['role' => 'author', 'position' => 0]);

        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Koreni,,Dobrica Ćosić:author,,9788652120789,,,,sr,Cyrl,paperback,Roman,',
        ])])->assertSuccessful();

        $this->assertDatabaseCount('books', 1);
    }

    public function test_postojece_kategorije_autori_i_izdavaci_se_ne_diraju(): void
    {
        $category = Category::factory()->create(['name' => 'Klasici', 'slug' => 'klasici', 'description' => 'moj opis', 'is_active' => false]);
        $author = Author::create(['name' => 'Ivo Andrić', 'slug' => 'ivo-andric', 'bio' => 'moja bio']);
        $publisher = Publisher::create(['name' => 'Vulkan izdavaštvo', 'slug' => 'vulkan-izdavastvo', 'website' => 'https://vulkan.example']);

        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([$this->fullRow()])])->assertSuccessful();

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('authors', 1);
        $this->assertDatabaseCount('publishers', 1);
        $this->assertSame('moj opis', $category->fresh()->description);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame('moja bio', $author->fresh()->bio);
        $this->assertSame('https://vulkan.example', $publisher->fresh()->website);
        $this->assertSame($category->id, Product::sole()->category_id);
    }

    public function test_parent_opcija_stavlja_nove_kategorije_pod_roditelja(): void
    {
        $parent = Category::factory()->create(['slug' => 'knjige']);

        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([$this->fullRow()]), '--parent' => 'knjige'])
            ->assertSuccessful();

        $this->assertSame($parent->id, Category::where('slug', 'klasici')->sole()->parent_id);
    }

    public function test_dry_run_prikazuje_plan_i_nista_ne_upisuje(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([$this->fullRow()]), '--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->expectsOutputToContain('placeholder cenu')
            ->assertSuccessful();

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('books', 0);
        $this->assertDatabaseCount('authors', 0);
        $this->assertDatabaseCount('publishers', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_nevalidan_red_se_preskace_a_ostali_se_uvoze(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Loš ISBN,,Ivo Andrić:author,,9788610034746,,,,sr,,paperback,Klasici,',
            'Loš format,,Ivo Andrić:author,,,,,,sr,,papirni,Klasici,',
            'Loša uloga,,Ivo Andrić:pisac,,,,,,sr,,paperback,Klasici,',
            $this->fullRow(),
        ])])->assertFailed();

        $this->assertDatabaseCount('books', 1);
        $this->assertSame('Na Drini ćuprija', Product::sole()->name);
    }

    public function test_slug_kolizija_se_resava_sufiksom_autora(): void
    {
        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Test,,Ivo Andrić:author,Laguna,9788610034745,,,,sr,,paperback,Klasici,',
        ])])->assertSuccessful();

        $this->artisan('catalog:import-books', ['csv' => $this->writeCsv([
            'Test!,,Drugi Autor:author,Nova izdavačka,,,,,sr,,paperback,Nova kategorija,',
        ])])->assertSuccessful();

        $this->assertSame(['test', 'test-drugi-autor'], Product::orderBy('id')->pluck('slug')->all());
    }

    public function test_csv_bez_obaveznih_kolona_ili_nepostojeci_fajl_su_nevalidni(): void
    {
        file_put_contents($this->csv, "title,authors\nX,Y:author\n");
        $this->artisan('catalog:import-books', ['csv' => $this->csv])->assertExitCode(2);

        $this->artisan('catalog:import-books', ['csv' => $this->csv.'.nema'])->assertExitCode(2);

        $this->assertDatabaseCount('books', 0);
    }

    public function test_bom_na_pocetku_fajla_ne_kvari_header(): void
    {
        file_put_contents($this->csv, "\xEF\xBB\xBF".self::HEADER."\n".$this->fullRow()."\n");

        $this->artisan('catalog:import-books', ['csv' => $this->csv])->assertSuccessful();

        $this->assertDatabaseCount('books', 1);
    }
}
