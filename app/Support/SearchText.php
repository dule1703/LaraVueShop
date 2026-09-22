<?php

namespace App\Support;

/**
 * Normalizacija za pretragu: mala slova, ćirilica preslovljena u latinicu,
 * dijakritika uklonjena (č/ć→c, š→s, ž→z, đ→dj), interpunkcija zamenjena
 * razmakom. "Дина" i "Dina" moraju dati isti rezultat.
 */
class SearchText
{
    private const MAP = [
        // ćirilica -> latinica bez dijakritike
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'ђ' => 'dj',
        'е' => 'e', 'ж' => 'z', 'з' => 'z', 'и' => 'i', 'ј' => 'j', 'к' => 'k',
        'л' => 'l', 'љ' => 'lj', 'м' => 'm', 'н' => 'n', 'њ' => 'nj', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'ћ' => 'c', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'c', 'џ' => 'dz', 'ш' => 's',
        // latinica sa dijakritikom -> bez dijakritike ("dž" postaje "dz" preko ž->z)
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
    ];

    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $lower = mb_strtolower($text, 'UTF-8');
        $transliterated = strtr($lower, self::MAP);
        $asciiOnly = preg_replace('/[^a-z0-9]+/u', ' ', $transliterated);

        return trim(preg_replace('/\s+/', ' ', $asciiOnly));
    }
}
