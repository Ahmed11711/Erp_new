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
        'دمور', 'ديمور', 'تول', 'ساتان', 'شمواه', 'فرو', 'ribbon', 'ريش',
        'بطان', 'قماش بطانة', 'leather', 'fabric', 'cloth', 'cotton',
    ];

    /** Things that commonly should NOT multiply by finished-product color */
    private const FIXED_HINTS = [
        'سوسته', 'سحاب', 'سحّاب', 'كبسول', 'كبسولة', 'برشام', 'براغي', 'مسامير',
        'صاموله', 'صامولة', 'لصق', 'غراء', 'سلك', 'شريط', 'حلقات', 'باكليت',
        'فوم',
    ];

    public static function guessFromLabel(?string $label): bool
    {
        if ($label === null || trim($label) === '') {
            return false;
        }

        $n = ArabicTextNormalizer::normalize($label);
        foreach (self::FIXED_HINTS as $hint) {
            if (str_contains($n, ArabicTextNormalizer::normalize($hint))) {
                return false;
            }
        }
        foreach (self::FABRIC_HINTS as $hint) {
            if (str_contains($n, ArabicTextNormalizer::normalize($hint))) {
                return true;
            }
        }

        return false;
    }
}
