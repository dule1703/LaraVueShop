<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Admin panel redizajn, korak 2, Deo 2: products.image se sad upisuje preko
 * pravog file upload-a (ProductImageUploader), ne preko URL text input-a.
 * `UploadedFile::fake()->create(...)` namerno, ne `->image()` — potonji
 * zahteva GD/Imagick ekstenziju koja nije dostupna u ovom okruženju
 * (potvrđeno pre pisanja testa: `php -m | grep -i gd` prazno).
 */
class ProductImageUploadTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->active()->create()->id,
            'name' => 'Test proizvod',
            'description' => null,
            'price' => 10,
            'stock' => 5,
            'is_active' => true,
        ], $overrides);
    }

    public function test_admin_moze_da_otpremi_sliku_pri_kreiranju_proizvoda(): void
    {
        $file = UploadedFile::fake()->create('korica.jpg', 100, 'image/jpeg');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->productPayload(['image' => $file]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.products.index'));

        $product = Product::where('name', 'Test proizvod')->firstOrFail();
        $this->assertNotNull($product->image);
        $this->assertStringContainsString('/storage/products/', $product->image);
        Storage::disk('public')->assertExists(Str::after($product->image, '/storage/'));
    }

    public function test_upload_odbija_pogresan_mime_tip(): void
    {
        $file = UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->productPayload(['image' => $file]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('products', ['name' => 'Test proizvod']);
    }

    public function test_upload_odbija_prevelik_fajl(): void
    {
        // max:2048 (KB) — 3000 KB je preko granice.
        $file = UploadedFile::fake()->create('korica.jpg', 3000, 'image/jpeg');

        $this->actingAs($this->admin())
            ->post(route('admin.products.store'), $this->productPayload(['image' => $file]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('products', ['name' => 'Test proizvod']);
    }

    public function test_izmena_bez_nove_slike_ne_menja_postojecu(): void
    {
        $product = Product::factory()->create(['image' => 'https://picsum.photos/seed/x/600/600']);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload([
                'category_id' => $product->category_id,
                'name' => $product->name,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://picsum.photos/seed/x/600/600', $product->fresh()->image);
    }

    public function test_remove_image_checkbox_brise_postojecu_sliku(): void
    {
        $path = UploadedFile::fake()->create('staro.jpg', 50, 'image/jpeg')->store('products', 'public');
        $url = Storage::disk('public')->url($path);
        $product = Product::factory()->create(['image' => $url]);

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload([
                'category_id' => $product->category_id,
                'name' => $product->name,
                'remove_image' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_nova_slika_zamenjuje_i_brise_staru_lokalnu_sliku(): void
    {
        $oldPath = UploadedFile::fake()->create('staro.jpg', 50, 'image/jpeg')->store('products', 'public');
        $oldUrl = Storage::disk('public')->url($oldPath);
        $product = Product::factory()->create(['image' => $oldUrl]);

        $newFile = UploadedFile::fake()->create('novo.jpg', 60, 'image/jpeg');

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload([
                'category_id' => $product->category_id,
                'name' => $product->name,
                'image' => $newFile,
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $product->fresh();
        $this->assertNotSame($oldUrl, $fresh->image);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_upload_ne_dira_eksterni_url_pri_zameni(): void
    {
        // Stari picsum.photos URL nije na našem disku — ne sme da se pokuša brisanje.
        $product = Product::factory()->create(['image' => 'https://picsum.photos/seed/external/600/600']);

        $newFile = UploadedFile::fake()->create('novo.jpg', 60, 'image/jpeg');

        $this->actingAs($this->admin())
            ->put(route('admin.products.update', $product), $this->productPayload([
                'category_id' => $product->category_id,
                'name' => $product->name,
                'image' => $newFile,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('/storage/products/', $product->fresh()->image);
    }

    public function test_knjiga_prihvata_otpremljenu_sliku(): void
    {
        $file = UploadedFile::fake()->create('korica.jpg', 100, 'image/jpeg');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['image' => $file]))
            ->assertSessionHasNoErrors();

        $product = Product::where('slug', 'prokleta-avlija')->firstOrFail();
        $this->assertStringContainsString('/storage/products/', $product->image);
    }

    public function test_knjiga_odbija_pogresan_mime_tip(): void
    {
        $file = UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin())
            ->post(route('admin.books.store'), $this->bookPayload(['image' => $file]))
            ->assertSessionHasErrors('image');
    }
}
