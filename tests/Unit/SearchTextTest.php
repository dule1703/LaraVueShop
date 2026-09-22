<?php

namespace Tests\Unit;

use App\Support\SearchText;
use PHPUnit\Framework\TestCase;

/**
 * Faza 4: normalizacija za pretragu — ćirilica i latinica moraju dati isti
 * rezultat, dijakritika se uklanja.
 */
class SearchTextTest extends TestCase
{
    public function test_cirilica_i_latinica_daju_isti_rezultat(): void
    {
        $this->assertSame(SearchText::normalize('Dina'), SearchText::normalize('Дина'));
        $this->assertSame(SearchText::normalize('Ivo Andrić'), SearchText::normalize('Иво Андрић'));
    }

    public function test_dijakritika_se_uklanja(): void
    {
        $this->assertSame('cetiri secera zutog dzema', SearchText::normalize('Četiri šećera žutog džema'));
    }

    public function test_velika_i_mala_slova_su_ista(): void
    {
        $this->assertSame(SearchText::normalize('PROLEĆE'), SearchText::normalize('proleće'));
    }

    public function test_interpunkcija_se_zamenjuje_razmakom_i_kolabira(): void
    {
        $this->assertSame('na drini cuprija', SearchText::normalize('Na  Drini  ćuprija!!!'));
        $this->assertSame('dr', SearchText::normalize('Dr.'));
    }

    public function test_null_i_prazan_string_daju_prazan_rezultat(): void
    {
        $this->assertSame('', SearchText::normalize(null));
        $this->assertSame('', SearchText::normalize(''));
        $this->assertSame('', SearchText::normalize('   '));
    }

    public function test_sva_cirilicna_slova_se_preslovljavaju(): void
    {
        $this->assertSame(
            'abvgddjezzijklljmnnjoprstcufhccdzs',
            SearchText::normalize('абвгдђежзијклљмнњопрстћуфхцчџш')
        );
    }
}
