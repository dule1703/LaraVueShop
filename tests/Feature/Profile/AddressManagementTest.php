<?php

namespace Tests\Feature\Profile;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sačuvane adrese (Profile stranica):
 *   - prva adresa je uvek podrazumevana, najviše jedna podrazumevana po korisniku
 *   - brisanje podrazumevane NE unapređuje drugu adresu (produktna odluka)
 *   - IDOR: lookup ide kroz $user->addresses(), pa tuđa adresa daje 404 i ostaje netaknuta
 *   - gost se preusmerava na login (standardni auth middleware)
 */
class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'recipient_name' => 'Pera Perić',
            'phone' => '0601234567',
            'line1' => 'Bulevar oslobođenja 1',
            'line2' => null,
            'city' => 'Novi Sad',
            'postal_code' => '21000',
            'country' => 'Srbija',
        ], $overrides);
    }

    private function makeAddress(User $user, array $overrides = []): Address
    {
        return Address::create($this->addressData($overrides) + [
            'user_id' => $user->id,
            'is_default' => false,
        ]);
    }

    public function test_ulogovan_korisnik_kreira_adresu(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('addresses.store'), $this->addressData())
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'recipient_name' => 'Pera Perić',
            'line1' => 'Bulevar oslobođenja 1',
            'city' => 'Novi Sad',
        ]);
    }

    public function test_prva_adresa_je_automatski_podrazumevana(): void
    {
        $user = User::factory()->create();

        // Bez is_default u zahtevu (i čak sa eksplicitnim false) — prva adresa je podrazumevana.
        $this->actingAs($user)
            ->post(route('addresses.store'), $this->addressData(['is_default' => false]))
            ->assertRedirect();

        $this->assertTrue(Address::where('user_id', $user->id)->firstOrFail()->is_default);
    }

    public function test_druga_adresa_nije_podrazumevana_bez_is_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->addressData(['line1' => 'Prva 1']));
        $this->actingAs($user)->post(route('addresses.store'), $this->addressData(['line1' => 'Druga 2']));

        $this->assertTrue(Address::where('line1', 'Prva 1')->firstOrFail()->is_default);
        $this->assertFalse(Address::where('line1', 'Druga 2')->firstOrFail()->is_default);
    }

    public function test_nova_podrazumevana_adresa_skida_oznaku_sa_prethodne(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->addressData(['line1' => 'Prva 1']));
        $this->actingAs($user)->post(route('addresses.store'), $this->addressData([
            'line1' => 'Druga 2',
            'is_default' => true,
        ]));

        $this->assertSame(1, Address::where('user_id', $user->id)->where('is_default', true)->count());
        $this->assertTrue(Address::where('line1', 'Druga 2')->firstOrFail()->is_default);
        $this->assertFalse(Address::where('line1', 'Prva 1')->firstOrFail()->is_default);
    }

    public function test_postavljanje_podrazumevane_adrese(): void
    {
        $user = User::factory()->create();
        $first = $this->makeAddress($user, ['line1' => 'Prva 1']);
        $first->update(['is_default' => true]);
        $second = $this->makeAddress($user, ['line1' => 'Druga 2']);

        $this->actingAs($user)
            ->patch(route('addresses.setDefault', $second->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, Address::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public function test_podrazumevana_adresa_drugog_korisnika_ostaje_netaknuta(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $otherDefault = $this->makeAddress($other);
        $otherDefault->update(['is_default' => true]);

        $mine = $this->makeAddress($user);

        $this->actingAs($user)->patch(route('addresses.setDefault', $mine->id))->assertRedirect();

        $this->assertTrue($otherDefault->fresh()->is_default);
    }

    public function test_ulogovan_korisnik_menja_svoju_adresu(): void
    {
        $user = User::factory()->create();
        $address = $this->makeAddress($user);

        $this->actingAs($user)
            ->patch(route('addresses.update', $address->id), $this->addressData([
                'line1' => 'Nova ulica 5',
                'city' => 'Beograd',
                'postal_code' => '11000',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $address->fresh();
        $this->assertSame('Nova ulica 5', $fresh->line1);
        $this->assertSame('Beograd', $fresh->city);
        $this->assertSame('11000', $fresh->postal_code);
    }

    public function test_brisanje_podrazumevane_ne_unapredjuje_drugu_adresu(): void
    {
        $user = User::factory()->create();
        $default = $this->makeAddress($user, ['line1' => 'Prva 1']);
        $default->update(['is_default' => true]);
        $other = $this->makeAddress($user, ['line1' => 'Druga 2']);

        $this->actingAs($user)
            ->delete(route('addresses.destroy', $default->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('addresses', ['id' => $default->id]);
        $this->assertFalse($other->fresh()->is_default);
        $this->assertSame(0, Address::where('user_id', $user->id)->where('is_default', true)->count());
    }

    public static function foreignAddressRoutes(): array
    {
        return [
            'update' => ['patch', 'addresses.update'],
            'destroy' => ['delete', 'addresses.destroy'],
            'setDefault' => ['patch', 'addresses.setDefault'],
        ];
    }

    #[DataProvider('foreignAddressRoutes')]
    public function test_drugi_korisnik_ne_moze_da_dira_tudju_adresu(string $method, string $routeName): void
    {
        $owner = User::factory()->create();
        $address = $this->makeAddress($owner, ['line1' => 'Originalna 1']);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->{$method}(route($routeName, $address->id), $this->addressData([
                'line1' => 'Hakovana 666',
                'is_default' => true,
            ]))
            ->assertNotFound();

        $fresh = $address->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('Originalna 1', $fresh->line1);
        $this->assertFalse($fresh->is_default);
        $this->assertSame(0, Address::where('user_id', $other->id)->count());
    }

    public static function guestRoutes(): array
    {
        return [
            'store' => ['post', 'addresses.store', false],
            'update' => ['patch', 'addresses.update', true],
            'destroy' => ['delete', 'addresses.destroy', true],
            'setDefault' => ['patch', 'addresses.setDefault', true],
        ];
    }

    #[DataProvider('guestRoutes')]
    public function test_gost_je_preusmeren_na_login(string $method, string $routeName, bool $needsId): void
    {
        $owner = User::factory()->create();
        $address = $this->makeAddress($owner);

        $url = $needsId ? route($routeName, $address->id) : route($routeName);

        $this->{$method}($url, $this->addressData())
            ->assertRedirect(route('login'));

        $this->assertSame(1, Address::count());
        $this->assertSame('Bulevar oslobođenja 1', $address->fresh()->line1);
    }

    public function test_nedostaje_obavezno_polje_vraca_422(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('addresses.store'), $this->addressData(['recipient_name' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_name']);

        $this->assertDatabaseCount('addresses', 0);
    }

    public function test_profile_stranica_prosledjuje_samo_sopstvene_adrese(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $mine = $this->makeAddress($user);
        $this->makeAddress(User::factory()->create());

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->has('addresses', 1)
                ->where('addresses.0.id', $mine->id)
            );
    }
}
