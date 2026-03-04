<?php

namespace App\Helpers;

/**
 * Normalize Latvian diacritics to ASCII for search (e.g. Pūces → Puces).
 * Search variants: both normalized and with diacritics so "Puces" finds "Pūces" and vice versa.
 */
class Latvian
{
    private const TO_ASCII = [
        'ā' => 'a', 'Ā' => 'A', 'č' => 'c', 'Č' => 'C', 'ē' => 'e', 'Ē' => 'E',
        'ģ' => 'g', 'Ģ' => 'G', 'ī' => 'i', 'Ī' => 'I', 'ķ' => 'k', 'Ķ' => 'K',
        'ļ' => 'l', 'Ļ' => 'L', 'ņ' => 'n', 'Ņ' => 'N', 'š' => 's', 'Š' => 'S',
        'ū' => 'u', 'Ū' => 'U', 'ž' => 'z', 'Ž' => 'Z',
    ];

    private const TO_DIACRITIC = [
        'a' => 'ā', 'A' => 'Ā', 'c' => 'č', 'C' => 'Č', 'e' => 'ē', 'E' => 'Ē',
        'g' => 'ģ', 'G' => 'Ģ', 'i' => 'ī', 'I' => 'Ī', 'k' => 'ķ', 'K' => 'Ķ',
        'l' => 'ļ', 'L' => 'Ļ', 'n' => 'ņ', 'N' => 'Ņ', 's' => 'š', 'S' => 'Š',
        'u' => 'ū', 'U' => 'Ū', 'z' => 'ž', 'Z' => 'Ž',
    ];

    public static function normalize(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }
        return strtr($s, self::TO_ASCII);
    }

    /**
     * Return search variants: normalized and one with Latvian diacritics (so both "Puces" and "Pūces" match).
     *
     * @return array<int, string>
     */
    public static function searchVariants(?string $s): array
    {
        if ($s === null || $s === '') {
            return [];
        }
        $normalized = strtr($s, self::TO_ASCII);
        $withDiacritic = strtr($normalized, self::TO_DIACRITIC);
        $variants = array_unique([$normalized, $withDiacritic]);

        return array_values($variants);
    }

    /**
     * SQL expression that normalizes a column to ASCII (Latvian → Latin) and lowercase,
     * for use in LIKE search. So "Pūces" and "puces" both match.
     * Supports: pgsql (translate), sqlite/mysql (REPLACE chain).
     */
    public static function sqlNormalizedColumn(string $driver, string $column): string
    {
        if ($driver === 'pgsql') {
            return "translate(LOWER(COALESCE({$column}, '')), 'āčēģīķļņšūž', 'acegiklnsuz')";
        }
        $lower = "LOWER(COALESCE({$column}, ''))";
        foreach (['ū'=>'u','ā'=>'a','č'=>'c','ē'=>'e','ģ'=>'g','ī'=>'i','ķ'=>'k','ļ'=>'l','ņ'=>'n','š'=>'s','ž'=>'z'] as $from => $to) {
            $lower = "REPLACE({$lower}, '{$from}', '{$to}')";
        }
        return $lower;
    }
}
