<?php

namespace App\Support;

final class Isbn
{
    /**
     * Uklanja razmake i crtice, X veliko. Prazan unos -> null.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = strtoupper(preg_replace('/[\s\-\x{00A0}\x{2010}-\x{2015}]+/u', '', $value));

        return $clean === '' ? null : $clean;
    }

    public static function isValid10(string $isbn): bool
    {
        if (! preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = $isbn[$i] === 'X' ? 10 : (int) $isbn[$i];
            $sum += $digit * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    public static function isValid13(string $isbn): bool
    {
        if (! preg_match('/^97[89]\d{10}$/', $isbn)) {
            return false;
        }

        return self::checkDigit13(substr($isbn, 0, 12)) === (int) $isbn[12];
    }

    /**
     * Prima već normalizovan unos.
     */
    public static function isValid(string $isbn): bool
    {
        return self::isValid13($isbn) || self::isValid10($isbn);
    }

    /**
     * Validan ISBN-10 ili ISBN-13 -> kanonski ISBN-13; nevalidan -> null.
     */
    public static function toIsbn13(string $isbn): ?string
    {
        if (self::isValid13($isbn)) {
            return $isbn;
        }

        if (self::isValid10($isbn)) {
            $base = '978'.substr($isbn, 0, 9);

            return $base.self::checkDigit13($base);
        }

        return null;
    }

    /**
     * ISBN-10 postoji samo za 978 prefiks.
     */
    public static function toIsbn10(string $isbn13): ?string
    {
        if (! self::isValid13($isbn13) || ! str_starts_with($isbn13, '978')) {
            return null;
        }

        $base = substr($isbn13, 3, 9);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $base[$i] * (10 - $i);
        }
        $check = (11 - $sum % 11) % 11;

        return $base.($check === 10 ? 'X' : (string) $check);
    }

    private static function checkDigit13(string $first12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $first12[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
