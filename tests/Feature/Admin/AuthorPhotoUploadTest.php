<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Admin panel redizajn, korak 3, Deo 1: authors.photo se sad upisuje preko
 * pravog file upload-a (ProductImageUploader, generalizovan sa parametrima
 * za polje/folder — vidi CLAUDE.md), ne preko URL text input-a. Manji skup
 * od ProductImageUploadTest-a (korak 2) — ista logika je već pokrivena tamo,
 * ovde samo potvrda da je isto ožičeno i za authors/'photo'/'authors' folder.
 */
class AuthorPhotoUploadTest extends TestCase
{
    use BuildsBookPayload;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    private function authorPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Autor',
            'slug' => '',
            'bio' => null,
        ], $overrides);
    }

    public function test_admin_moze_da_otpremi_fotografiju_pri_kreiranju_autora(): void
    {
        $file = UploadedFile::fake()->create('foto.jpg', 100, 'image/jpeg');

        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), $this->authorPayload(['photo' => $file]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.authors.index'));

        $author = Author::where('name', 'Test Autor')->firstOrFail();
        $this->assertNotNull($author->photo);
        $this->assertStringContainsString('/storage/authors/', $author->photo);
        Storage::disk('public')->assertExists(Str::after($author->photo, '/storage/'));
    }

    public function test_izmena_bez_nove_fotografije_ne_menja_postojecu(): void
    {
        $author = Author::factory()->create(['photo' => 'https://picsum.photos/seed/x/200/200']);

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), $this->authorPayload([
                'name' => $author->name,
                'slug' => $author->slug,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('https://picsum.photos/seed/x/200/200', $author->fresh()->photo);
    }

    public function test_remove_photo_checkbox_brise_postojecu_fotografiju(): void
    {
        $path = UploadedFile::fake()->create('staro.jpg', 50, 'image/jpeg')->store('authors', 'public');
        $url = Storage::disk('public')->url($path);
        $author = Author::factory()->create(['photo' => $url]);

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), $this->authorPayload([
                'name' => $author->name,
                'slug' => $author->slug,
                'remove_photo' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($author->fresh()->photo);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_nova_fotografija_zamenjuje_i_brise_staru_lokalnu_fotografiju(): void
    {
        $oldPath = UploadedFile::fake()->create('staro.jpg', 50, 'image/jpeg')->store('authors', 'public');
        $oldUrl = Storage::disk('public')->url($oldPath);
        $author = Author::factory()->create(['photo' => $oldUrl]);

        $newFile = UploadedFile::fake()->create('novo.jpg', 60, 'image/jpeg');

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), $this->authorPayload([
                'name' => $author->name,
                'slug' => $author->slug,
                'photo' => $newFile,
            ]))
            ->assertSessionHasNoErrors();

        $fresh = $author->fresh();
        $this->assertNotSame($oldUrl, $fresh->photo);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_upload_ne_dira_eksterni_url_pri_zameni(): void
    {
        // Isti gotcha kao ProductImageUploadTest — eksterni URL nije na
        // našem disku, ne sme da se pokuša brisanje.
        $author = Author::factory()->create(['photo' => 'https://picsum.photos/seed/external/200/200']);

        $newFile = UploadedFile::fake()->create('novo.jpg', 60, 'image/jpeg');

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), $this->authorPayload([
                'name' => $author->name,
                'slug' => $author->slug,
                'photo' => $newFile,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('/storage/authors/', $author->fresh()->photo);
    }
}
