<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faza 1: hijerarhija kategorija (parent_id) i redosled (position).
 */
class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nova_kategorija_je_koren_sa_pozicijom_nula(): void
    {
        $category = Category::factory()->create();

        $this->assertNull($category->parent_id);
        $this->assertSame(0, $category->fresh()->position);
    }

    public function test_podkategorije_se_vracaju_po_poziciji(): void
    {
        $parent = Category::factory()->create();
        $druga = Category::factory()->childOf($parent)->create(['position' => 2]);
        $prva = Category::factory()->childOf($parent)->create(['position' => 1]);

        $this->assertSame([$prva->id, $druga->id], $parent->children->pluck('id')->all());
        $this->assertTrue($prva->parent->is($parent));
    }

    public function test_brisanje_roditelja_podkategoriju_pretvara_u_koren(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $parent->delete();

        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
    }
}
