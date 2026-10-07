<?php

namespace Tests\Feature\Admin;

use App\Models\Book;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookPayload;
use Tests\TestCase;

/**
 * Regresija: admin lista knjiga nije učitavala products.image pa su sve
 * korice padale na tipografski placeholder.
 */
class BookIndexImageTest extends TestCase
{
    use RefreshDatabase;
    use BuildsBookPayload;

    public function test_admin_lista_knjiga_salje_sliku_proizvoda(): void
    {
        $this->withoutVite();

        $product = Product::factory()->for(Category::factory()->active()->create())
            ->create(['image' => 'https://picsum.photos/seed/knjiga/300/400']);
        Book::factory()->for($product, 'product')->create();

        $response = $this->actingAs($this->admin())->get(route('admin.books.index'));

        $response->assertOk();
        $this->assertSame(
            'https://picsum.photos/seed/knjiga/300/400',
            $response->viewData('page')['props']['books']['data'][0]['product']['image']
        );
    }
}
