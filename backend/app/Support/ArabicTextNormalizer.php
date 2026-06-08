<?php

namespace App\Support;

/**
 * Shared Arabic-aware label normalizer (product names, colors, units).
 *
 * Mirrors the rules previously embedded in RecipeSheetImportService so that
 * matching stays consistent across import, BOM resolution, and manufacturing.
 */
final class ArabicTextNormalizer
{
    public static function normalize(string $raw): string
    {
        $s = $raw;

        $s = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $s) ?? $s;
        $s = str_replace("\u{0640}", '', $s);

        $s = preg_replace('/[\x{0622}\x{0623}\x{0625}\x{0671}]/u', "\u{0627}", $s) ?? $s;

        $s = str_replace(["\u{0649}", "\u{0626}"], "\u{064A}", $s);

        $s = str_replace("\u{0629}", "\u{0647}", $s);

        $s = strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        $s = preg_replace(
            '/[\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200D}\x{202F}\x{205F}\x{2060}\x{3000}\x{FEFF}]/u',
            ' ',
            $s
        ) ?? $s;

        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);

        return mb_strtolower($s, 'UTF-8');
    }
}
