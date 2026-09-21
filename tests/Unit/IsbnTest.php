<?php

namespace Tests\Unit;

use App\Rules\ValidIsbn;
use App\Support\Isbn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    public static function validIsbn13(): array
    {
        return [
            'wikipedia primer' => ['9780306406157'],
            'clean code' => ['9780132350884'],
            'ostali primer' => ['9781234567897'],
            '979 prefiks' => ['9791090636071'],
        ];
    }

    public static function validIsbn10(): array
    {
        return [
            'kontrolna cifra 2' => ['0306406152'],
            'kontrolna cifra X' => ['080442957X'],
        ];
    }

    public static function invalidIsbn(): array
    {
        return [
            'pogrešna kontrolna cifra 13' => ['9780306406158'],
            'pogrešna kontrolna cifra 10' => ['0306406153'],
            'X na pogrešnom mestu' => ['08044295X7'],
            'slovo umesto cifre' => ['080442957Y'],
            'prekratak' => ['978030640615'],
            'predugačak' => ['97803064061570'],
            'prefiks van 978/979 sa ispravnom sumom' => ['9770306406158'],
            'prazan' => [''],
            'tekst' => ['nije-isbn'],
        ];
    }

    #[DataProvider('validIsbn13')]
    public function test_validan_isbn13_prolazi(string $isbn): void
    {
        $this->assertTrue(Isbn::isValid13($isbn));
        $this->assertTrue(Isbn::isValid($isbn));
    }

    #[DataProvider('validIsbn10')]
    public function test_validan_isbn10_prolazi(string $isbn): void
    {
        $this->assertTrue(Isbn::isValid10($isbn));
        $this->assertTrue(Isbn::isValid($isbn));
    }

    #[DataProvider('invalidIsbn')]
    public function test_nevalidan_isbn_pada(string $isbn): void
    {
        $this->assertFalse(Isbn::isValid($isbn));
        $this->assertNull(Isbn::toIsbn13($isbn));
    }

    public function test_isbn10_se_ne_prihvata_kao_isbn13_i_obrnuto(): void
    {
        $this->assertFalse(Isbn::isValid13('0306406152'));
        $this->assertFalse(Isbn::isValid10('9780306406157'));
    }

    public function test_normalizacija_uklanja_crtice_i_razmake_i_podize_x(): void
    {
        $this->assertSame('9780306406157', Isbn::normalize('978-0-306-40615-7'));
        $this->assertSame('9780306406157', Isbn::normalize(' 978 0306 406157 '));
        $this->assertSame('080442957X', Isbn::normalize('0-8044-2957-x'));
        $this->assertSame('9780306406157', Isbn::normalize("978\u{2010}0306406157"));
        $this->assertNull(Isbn::normalize('  - '));
        $this->assertNull(Isbn::normalize(null));
    }

    public function test_normalizacija_ne_menja_nevalidan_sadrzaj_tiho(): void
    {
        $this->assertSame('978ABC', Isbn::normalize('978-abc'));
    }

    public function test_konverzija_isbn10_u_isbn13(): void
    {
        $this->assertSame('9780306406157', Isbn::toIsbn13('0306406152'));
        $this->assertSame('9780804429573', Isbn::toIsbn13('080442957X'));
        $this->assertSame('9780306406157', Isbn::toIsbn13('9780306406157'));
    }

    public function test_konverzija_isbn13_u_isbn10(): void
    {
        $this->assertSame('0306406152', Isbn::toIsbn10('9780306406157'));
        $this->assertSame('080442957X', Isbn::toIsbn10('9780804429573'));
        $this->assertNull(Isbn::toIsbn10('9791090636071'), '979 prefiks nema ISBN-10');
        $this->assertNull(Isbn::toIsbn10('9780306406158'));
    }

    public function test_pravilo_validacije_normalizuje_i_odbija_nevalidno(): void
    {
        $rule = new ValidIsbn;
        $failures = [];
        $fail = function (string $message) use (&$failures) {
            $failures[] = $message;
        };

        $rule->validate('isbn', '978-0-306-40615-7', $fail);
        $rule->validate('isbn', '0-8044-2957-X', $fail);
        $this->assertSame([], $failures);

        $rule->validate('isbn', '978-0-306-40615-8', $fail);
        $rule->validate('isbn', ['9780306406157'], $fail);
        $this->assertCount(2, $failures);
    }
}
