<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin panel redizajn, korak 1 (Deo 1): Dashboard.vue dobija stvaran
 * sadržaj samo za admin korisnike (`stats` prop) — obični korisnici i dalje
 * vide samo jednostavnu dobrodošlicu (`stats === null`).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_vidi_brojke_na_dashboard_u(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->active()->create();

        Product::factory()->for($category)->create(['stock' => 10]);
        Product::factory()->for($category)->create(['stock' => 2]);

        Order::create([
            'first_name' => 'Pera', 'last_name' => 'Perić',
            'customer_email' => 'pera@example.com', 'total_price' => 10,
            'status' => 'pending', 'payment_method' => 'cod',
        ]);
        Order::create([
            'first_name' => 'Mika', 'last_name' => 'Mikić',
            'customer_email' => 'mika@example.com', 'total_price' => 20,
            'status' => 'paid', 'payment_method' => 'cod',
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.total_orders', 2)
                ->where('stats.pending_orders', 1)
                ->where('stats.total_products', 2)
                ->where('stats.low_stock_products', 1)
            );
    }

    public function test_obican_korisnik_ne_vidi_brojke(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats', null)
            );
    }
}
