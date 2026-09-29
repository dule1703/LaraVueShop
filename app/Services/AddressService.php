<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sačuvane adrese isporuke: sva upisivanja idu isključivo kroz ovaj servis
 * (isti obrazac kao BookService/InventoryService). Pravilo "najviše jedna
 * podrazumevana adresa po korisniku" sprovodi JEDAN put — setDefault().
 */
class AddressService
{
    private const FIELDS = ['recipient_name', 'phone', 'line1', 'line2', 'city', 'postal_code', 'country'];

    /**
     * Prva adresa korisnika je UVEK podrazumevana, bez obzira na
     * $data['is_default'] — nema druge adrese u odnosu na koju bi bila
     * "ne-podrazumevana". Za svaku sledeću, is_default iz zahteva ide kroz
     * setDefault() (koji skida oznaku sa prethodne).
     */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $isFirst = $user->addresses()->doesntExist();

            $address = $user->addresses()->create(
                $this->fields($data) + ['is_default' => $isFirst]
            );

            if (! $isFirst && ($data['is_default'] ?? false)) {
                $this->setDefault($address);
            }

            return $address->refresh();
        });
    }

    /**
     * is_default se namerno NE upisuje kroz update() — jedini put do
     * podrazumevane adrese je setDefault(), da postoji tačno jedno mesto koje
     * garantuje "samo jedna podrazumevana po korisniku".
     */
    public function update(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            $address->update($this->fields($data));

            if ($data['is_default'] ?? false) {
                $this->setDefault($address);
            }

            return $address->refresh();
        });
    }

    public function setDefault(Address $address): void
    {
        DB::transaction(function () use ($address) {
            Address::where('user_id', $address->user_id)
                ->whereKeyNot($address->id)
                ->update(['is_default' => false]);

            Address::whereKey($address->id)->update(['is_default' => true]);
        });

        $address->is_default = true;
        $address->syncOriginalAttribute('is_default');
    }

    /**
     * Namerno NE unapređuje drugu adresu u podrazumevanu kad se obriše
     * podrazumevana — produktna odluka, ne propust: korisnik sam bira sledeću.
     */
    public function delete(Address $address): void
    {
        $address->delete();
    }

    /**
     * Samo polja adrese (bez user_id/is_default) — prazan country pada na
     * podrazumevanu vrednost kolone ('Srbija'), prazan line2 ostaje NULL.
     */
    private function fields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('country', $fields) && blank($fields['country'])) {
            $fields['country'] = 'Srbija';
        }

        if (array_key_exists('line2', $fields) && blank($fields['line2'])) {
            $fields['line2'] = null;
        }

        return $fields;
    }
}
