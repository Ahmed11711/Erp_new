<?php

namespace App\Services\Manufacturing;

use App\Support\ArabicTextNormalizer;

/**
 * Heuristic ONLY — admins can flip supports_color manually on master items.
 * Fabrics/leathers behave as color-follow; fasteners/consumables stay fixed SKUs.
 */
final class SupportsColorEstimator
{
    private const FABRIC_HINTS = [
        'قماش', 'خيش', 'جلد', 'مخمل', 'قطن', 'كتان', 'دنيم', 'جينز', 'جدول', // typo safety
        'تول', 'ساتان', 'شمواه', 'فرو', 'ribbon', 'ريش',
        'بطان', 'قماش بطانة', 'leather', 'fabric', 'cloth', 'cotton',
    ];

    /** Things that commonly should NOT multiply by finished-product color */
    private const FIXED_HINTS = [
        'دمور', 'ديمور',
        'سوسته', 'سحاب', 'سحّاب', 'كبسول', 'كبسولة', 'برشام', 'براغي', 'مسامير',
        'صاموله', 'صامولة', 'لصق', 'غراء', 'سلك', 'شريط', 'حلقات', 'باكليت',
        'فوم', 'جرار', 'فايبر', 'fiber',
    ];

    public static function guessFromLabel(?string $label): bool
    {
        return self::followsProductionColor($label, false);
    }

    /**
     * Decide whether a BOM master should issue a color-specific child at production time.
     *
     * Name heuristics override a stale supports_color flag (e.g. دمور wrongly flagged true).
     */
    public static function followsProductionColor(?string $label, bool $supportsColorFlag): bool
    {
        if ($label === null || trim($label) === '') {
            return $supportsColorFlag;
        }

        if (self::matchesFixedHint($label)) {
            return false;
        }

        if (self::matchesFabricHint($label)) {
            return true;
        }

        return $supportsColorFlag;
    }

    private static function matchesFixedHint(string $label): bool
    {
        $n = ArabicTextNormalizer::normalize($label);
        foreach (self::FIXED_HINTS as $hint) {
            if (str_contains($n, ArabicTextNormalizer::normalize($hint))) {
                return true;
            }
        }

        return false;
    }

    private static function matchesFabricHint(string $label): bool
    {
        $n = ArabicTextNormalizer::normalize($label);
        foreach (self::FABRIC_HINTS as $hint) {
            if (str_contains($n, ArabicTextNormalizer::normalize($hint))) {
                return true;
            }
        }

        return false;
    }
}
