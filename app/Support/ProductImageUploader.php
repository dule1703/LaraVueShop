<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Deljena logika za upload jedne slike preko file input-a — Admin/Products i
 * Admin/Books (preko BookRequest/BookService, products.image kolona) i
 * Admin/Authors (authors.photo kolona) je koriste, uvek na isti 'public' disk.
 * Parametrizovano ($fileField/$removeField/$folder, koraк 3) umesto duple
 * klase po tipu entiteta — svi pozivi bez tih argumenata (Products/Books,
 * postojeći od koraka 2) zadržavaju stare default-e ('image'/'remove_image'/
 * 'products'), ponašanje im nepromenjeno.
 *
 * Tri ishoda iz jednog request-a, u ovom redosledu:
 *   1. Nov fajl otpremljen ($fileField) — sačuvaj ga, obriši stari LOKALNI
 *      fajl (ako je bio na 'public' disku; eksterni URL, npr. stari
 *      picsum.photos unos, se ne dira — nemamo ga na disku da bismo ga obrisali).
 *   2. $removeField checkbox — obriši trenutnu sliku (isto lokalno-only
 *      pravilo), postavi na NULL.
 *   3. Ni jedno ni drugo — vrati POSTOJEĆU vrednost nedirnutu. Bitno za Edit
 *      forme: `<input type="file">` se ne može unapred popuniti postojećim
 *      URL-om (HTML ograničenje), pa "ništa nije izabrano" mora da znači
 *      "ne diraj sliku", ne "obriši je".
 */
class ProductImageUploader
{
    public static function resolve(
        Request $request,
        ?string $currentImage,
        string $fileField = 'image',
        string $removeField = 'remove_image',
        string $folder = 'products',
    ): ?string {
        if ($request->hasFile($fileField)) {
            self::deleteIfLocal($currentImage);

            $path = $request->file($fileField)->store($folder, 'public');

            return Storage::disk('public')->url($path);
        }

        if ($request->boolean($removeField)) {
            self::deleteIfLocal($currentImage);

            return null;
        }

        return $currentImage;
    }

    private static function deleteIfLocal(?string $image): void
    {
        if ($image === null) {
            return;
        }

        // Izvedeno iz samog diska (ne config('app.url') konkatenacija) — u
        // testovima Storage::fake() gubi eksplicitan 'url' config i vraća
        // relativnu putanju umesto pune, pa bi hardkodovan prefiks tiho
        // promašio poređenje i preskočio brisanje.
        $prefix = Storage::disk('public')->url('');

        if (! str_starts_with($image, $prefix)) {
            return;
        }

        Storage::disk('public')->delete(substr($image, strlen($prefix)));
    }
}
