<?php

namespace Tests\Feature\Catalog;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use App\Models\Publisher;
use App\Services\BookCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faza 3.1: javni katalog (/shop) — paginacija i filteri.
 */
class ShopCatalogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $book
     */
    private function makeBook(array $product = [], array $book = [], ?Publisher $publisher = null): Book
    {
        $factory = Book::factory()->for(Product::factory()->state($product));

        if ($publisher !== null) {
            $factory = $factory->for($publisher);
        }

        return $factory->create($book);
    }

    /** @return list<string> */
    private function titles(TestResponse $response): array
    {
        return collect($response->viewData('page')['props']['books']['data'])->pluck('title')->all();
    }

    private function shop(array $query = []): TestResponse
    {
        return $this->get(route('shop', $query))->assertOk();
    }

    public function test_paginacija_vraca_tacan_broj_knjiga_po_strani(): void
    {
        foreach (range(1, 30) as $i) {
            $this->makeBook(['name' => sprintf('Knjiga %02d', $i), 'slug' => sprintf('knjiga-%02d', $i)]);
        }

        $this->shop()->assertInertia(fn (Assert $page) => $page
            ->component('Shop')
            ->has('books.data', BookCatalog::PER_PAGE)
            ->where('books.total', 30)
            ->where('books.per_page', BookCatalog::PER_PAGE)
            ->where('books.last_page', 3)
            ->where('books.data.0.title', 'Knjiga 01'));

        $this->shop(['page' => 3])->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 30 - 2 * BookCatalog::PER_PAGE)
            ->where('books.current_page', 3)
            ->where('books.data.0.title', 'Knjiga 25'));
    }

    public function test_strane_se_ne_preklapaju_i_pokrivaju_ceo_katalog(): void
    {
        foreach (range(1, 30) as $i) {
            $this->makeBook(['name' => sprintf('Knjiga %02d', $i), 'slug' => sprintf('knjiga-%02d', $i)]);
        }

        $all = [];
        foreach ([1, 2, 3] as $page) {
            $all = array_merge($all, $this->titles($this->shop(['page' => $page])));
        }

        $this->assertCount(30, $all);
        $this->assertCount(30, array_unique($all));
    }

    public function test_linkovi_paginacije_cuvaju_aktivne_filtere(): void
    {
        foreach (range(1, 15) as $i) {
            $this->makeBook([], ['format' => 'paperback']);
        }

        $links = $this->shop(['format' => 'paperback'])->viewData('page')['props']['books']['links'];

        $this->assertStringContainsString('format=paperback', collect($links)->firstWhere('label', '2')['url']);
    }

    public function test_pocetna_i_shop_prikazuju_isti_katalog(): void
    {
        $this->makeBook(['name' => 'Jedina']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Shop')->where('books.data.0.title', 'Jedina'));
    }

    public function test_prikazuju_se_samo_aktivne_knjige(): void
    {
        $this->makeBook(['name' => 'Aktivna']);
        $this->makeBook(['name' => 'Neaktivna', 'is_active' => false]);
        Product::factory()->create(['name' => 'Proizvod bez knjige']);

        $this->assertSame(['Aktivna'], $this->titles($this->shop()));
    }

    public function test_kartica_knjige_prikazuje_samo_autore_sa_ulogom_author_i_slug(): void
    {
        $book = $this->makeBook(['name' => 'Prevedena knjiga', 'slug' => 'prevedena-knjiga']);
        $pisac = Author::factory()->create(['name' => 'Pisac']);
        $prevodilac = Author::factory()->create(['name' => 'Prevodilac']);
        $book->authors()->attach($pisac->id, ['role' => 'author', 'position' => 0]);
        $book->authors()->attach($prevodilac->id, ['role' => 'translator', 'position' => 1]);

        $this->shop()->assertInertia(fn (Assert $page) => $page
            ->where('books.data.0.slug', 'prevedena-knjiga')
            ->where('books.data.0.authors', ['Pisac']));
    }

    public function test_filter_po_kategoriji_ukljucuje_potkategorije(): void
    {
        $romani = Category::factory()->active()->create(['slug' => 'romani']);
        $istorijski = Category::factory()->active()->childOf($romani)->create(['slug' => 'istorijski-romani']);
        $poezija = Category::factory()->active()->create(['slug' => 'poezija']);

        $this->makeBook(['name' => 'Roman', 'category_id' => $romani->id]);
        $this->makeBook(['name' => 'Istorijski roman', 'category_id' => $istorijski->id]);
        $this->makeBook(['name' => 'Pesme', 'category_id' => $poezija->id]);

        $this->assertEqualsCanonicalizing(['Roman', 'Istorijski roman'], $this->titles($this->shop(['category' => 'romani'])));
        $this->assertSame(['Istorijski roman'], $this->titles($this->shop(['category' => 'istorijski-romani'])));
        $this->assertSame(['Pesme'], $this->titles($this->shop(['category' => 'poezija'])));
    }

    public function test_filter_po_autoru_pronalazi_knjige_bez_obzira_na_ulogu(): void
    {
        $andric = Author::factory()->create(['slug' => 'ivo-andric']);
        $drugi = Author::factory()->create(['slug' => 'drugi-autor']);
        $a = $this->makeBook(['name' => 'Autorova']);
        $b = $this->makeBook(['name' => 'Prevedena']);
        $c = $this->makeBook(['name' => 'Tuđa']);
        $a->authors()->attach($andric->id, ['role' => 'author', 'position' => 0]);
        $b->authors()->attach($andric->id, ['role' => 'translator', 'position' => 0]);
        $c->authors()->attach($drugi->id, ['role' => 'author', 'position' => 0]);

        $this->assertEqualsCanonicalizing(['Autorova', 'Prevedena'], $this->titles($this->shop(['author' => 'ivo-andric'])));
    }

    public function test_filter_po_izdavacu(): void
    {
        $vulkan = Publisher::factory()->create(['slug' => 'vulkan']);
        $laguna = Publisher::factory()->create(['slug' => 'laguna']);
        $this->makeBook(['name' => 'Vulkanova'], [], $vulkan);
        $this->makeBook(['name' => 'Lagunina'], [], $laguna);
        $this->makeBook(['name' => 'Bez izdavača'], ['publisher_id' => null]);

        $this->assertSame(['Vulkanova'], $this->titles($this->shop(['publisher' => 'vulkan'])));
    }

    public function test_filter_po_jeziku(): void
    {
        $this->makeBook(['name' => 'Srpska'], ['language' => 'sr']);
        $this->makeBook(['name' => 'Engleska'], ['language' => 'en']);

        $this->assertSame(['Engleska'], $this->titles($this->shop(['language' => 'en'])));
    }

    public function test_filter_po_pismu(): void
    {
        $this->makeBook(['name' => 'Cirilica'], ['script' => 'Cyrl']);
        $this->makeBook(['name' => 'Latinica'], ['script' => 'Latn']);

        $this->assertSame(['Cirilica'], $this->titles($this->shop(['script' => 'Cyrl'])));
        $this->assertSame(['Latinica'], $this->titles($this->shop(['script' => 'Latn'])));
    }

    public function test_filter_po_formatu(): void
    {
        $this->makeBook(['name' => 'Tvrda'], ['format' => 'hardcover']);
        $this->makeBook(['name' => 'Meka'], ['format' => 'paperback']);
        Book::factory()->ebook()->create();

        $this->assertSame(['Tvrda'], $this->titles($this->shop(['format' => 'hardcover'])));
        $this->assertCount(1, $this->titles($this->shop(['format' => 'ebook'])));
    }

    public function test_filter_po_opsegu_cene_je_inkluzivan(): void
    {
        $this->makeBook(['name' => 'Jeftina', 'price' => 5]);
        $this->makeBook(['name' => 'Srednja', 'price' => 10]);
        $this->makeBook(['name' => 'Skupa', 'price' => 20]);

        $this->assertEqualsCanonicalizing(['Srednja', 'Skupa'], $this->titles($this->shop(['price_min' => 10])));
        $this->assertEqualsCanonicalizing(['Jeftina', 'Srednja'], $this->titles($this->shop(['price_max' => 10])));
        $this->assertSame(['Srednja'], $this->titles($this->shop(['price_min' => 10, 'price_max' => 10])));
        $this->assertSame(['Srednja'], $this->titles($this->shop(['price_min' => 6, 'price_max' => 19.99])));
    }

    public function test_filter_na_stanju_racuna_neogranicene_zalihe_kao_dostupne(): void
    {
        $this->makeBook(['name' => 'Ima', 'stock' => 3]);
        $this->makeBook(['name' => 'Nema', 'stock' => 0]);
        $ebook = Book::factory()->ebook()->create();
        $ebook->product->update(['name' => 'E-knjiga']);

        $this->assertEqualsCanonicalizing(['Ima', 'E-knjiga'], $this->titles($this->shop(['in_stock' => 1])));
        $this->assertCount(3, $this->titles($this->shop()));
        $this->assertCount(3, $this->titles($this->shop(['in_stock' => 0])));
    }

    public function test_nepoznat_slug_daje_nula_rezultata_a_ne_ceo_katalog(): void
    {
        $this->makeBook();

        foreach (['category', 'author', 'publisher'] as $key) {
            $this->assertSame([], $this->titles($this->shop([$key => 'nepostojeci-slug'])), $key);
        }
    }

    public function test_neaktivna_kategorija_ne_daje_rezultate(): void
    {
        $skrivena = Category::factory()->inactive()->create(['slug' => 'skrivena']);
        $this->makeBook(['category_id' => $skrivena->id]);

        $this->assertSame([], $this->titles($this->shop(['category' => 'skrivena'])));
    }

    public function test_filteri_se_kombinuju_kao_logicko_i(): void
    {
        $romani = Category::factory()->active()->create(['slug' => 'romani']);
        $autor = Author::factory()->create(['slug' => 'autor-x']);

        $trazena = $this->makeBook(
            ['name' => 'Trazena', 'category_id' => $romani->id, 'price' => 15, 'stock' => 4],
            ['format' => 'paperback', 'script' => 'Cyrl', 'language' => 'sr'],
        );
        $trazena->authors()->attach($autor->id, ['role' => 'author', 'position' => 0]);

        // Svaka od ovih se razlikuje u tačno jednom kriterijumu.
        $this->makeBook(['name' => 'Drugi format', 'category_id' => $romani->id, 'price' => 15, 'stock' => 4], ['format' => 'hardcover', 'script' => 'Cyrl', 'language' => 'sr'])
            ->authors()->attach($autor->id, ['role' => 'author', 'position' => 0]);
        $this->makeBook(['name' => 'Preskupa', 'category_id' => $romani->id, 'price' => 40, 'stock' => 4], ['format' => 'paperback', 'script' => 'Cyrl', 'language' => 'sr'])
            ->authors()->attach($autor->id, ['role' => 'author', 'position' => 0]);
        $this->makeBook(['name' => 'Nema zaliha', 'category_id' => $romani->id, 'price' => 15, 'stock' => 0], ['format' => 'paperback', 'script' => 'Cyrl', 'language' => 'sr'])
            ->authors()->attach($autor->id, ['role' => 'author', 'position' => 0]);
        $this->makeBook(['name' => 'Bez autora', 'category_id' => $romani->id, 'price' => 15, 'stock' => 4], ['format' => 'paperback', 'script' => 'Cyrl', 'language' => 'sr']);
        $this->makeBook(['name' => 'Latinica', 'category_id' => $romani->id, 'price' => 15, 'stock' => 4], ['format' => 'paperback', 'script' => 'Latn', 'language' => 'sr'])
            ->authors()->attach($autor->id, ['role' => 'author', 'position' => 0]);

        $response = $this->shop([
            'category' => 'romani', 'author' => 'autor-x', 'language' => 'sr', 'script' => 'Cyrl',
            'format' => 'paperback', 'price_min' => 10, 'price_max' => 20, 'in_stock' => 1,
        ]);

        $this->assertSame(['Trazena'], $this->titles($response));
        $response->assertInertia(fn (Assert $page) => $page->where('books.total', 1));
    }

    public function test_neispravne_vrednosti_filtera_se_ignorisu(): void
    {
        $this->makeBook(['name' => 'A']);
        $this->makeBook(['name' => 'B']);

        $this->shop(['script' => 'xyz', 'format' => 'papir', 'price_min' => 'abc', 'price_max' => -5, 'language' => 'SRPSKI', 'author' => ['a', 'b']])
            ->assertInertia(fn (Assert $page) => $page
                ->has('books.data', 2)
                ->where('filters.script', null)
                ->where('filters.format', null)
                ->where('filters.price_min', null)
                ->where('filters.price_max', null)
                ->where('filters.language', null)
                ->where('filters.author', null));
    }

    public function test_izabrani_filteri_se_vracaju_stranici(): void
    {
        $this->shop(['format' => 'ebook', 'price_min' => '5', 'in_stock' => '1'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.format', 'ebook')
                ->where('filters.price_min', '5')
                ->where('filters.in_stock', true)
                ->where('filters.category', null));
    }

    public function test_opcije_filtera_sadrze_samo_entitete_sa_aktivnim_knjigama(): void
    {
        $aktivan = Author::factory()->create(['name' => 'Aktivan autor']);
        $neaktivan = Author::factory()->create(['name' => 'Neaktivan autor']);
        Author::factory()->create(['name' => 'Bez knjiga']);
        $izdavacAktivan = Publisher::factory()->create(['name' => 'Aktivan izdavač']);
        $izdavacNeaktivan = Publisher::factory()->create(['name' => 'Neaktivan izdavač']);

        $this->makeBook([], ['language' => 'sr'], $izdavacAktivan)
            ->authors()->attach($aktivan->id, ['role' => 'author', 'position' => 0]);
        $this->makeBook(['is_active' => false], ['language' => 'de'], $izdavacNeaktivan)
            ->authors()->attach($neaktivan->id, ['role' => 'author', 'position' => 0]);

        $this->shop()->assertInertia(fn (Assert $page) => $page
            ->where('options.authors', fn ($authors) => collect($authors)->pluck('name')->all() === ['Aktivan autor'])
            ->where('options.publishers', fn ($publishers) => collect($publishers)->pluck('name')->all() === ['Aktivan izdavač'])
            ->where('options.languages', ['sr'])
            ->where('options.scripts', Book::SCRIPTS)
            ->where('options.formats', Book::FORMATS));
    }

    public function test_opcije_kategorija_su_stablo_sa_dubinom_i_bez_neaktivnih(): void
    {
        $romani = Category::factory()->active()->create(['name' => 'Romani', 'slug' => 'romani', 'position' => 1]);
        Category::factory()->active()->childOf($romani)->create(['name' => 'Istorijski', 'slug' => 'istorijski']);
        Category::factory()->active()->create(['name' => 'Poezija', 'slug' => 'poezija', 'position' => 2]);
        Category::factory()->inactive()->create(['name' => 'Skrivena', 'slug' => 'skrivena', 'position' => 3]);

        $this->shop()->assertInertia(fn (Assert $page) => $page
            ->where('options.categories', fn ($categories) => collect($categories)
                ->map(fn ($c) => [$c['slug'], $c['depth']])->all() === [['romani', 0], ['istorijski', 1], ['poezija', 0]]));
    }
}
