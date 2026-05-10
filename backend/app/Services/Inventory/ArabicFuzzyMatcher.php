<?php

namespace App\Services\Inventory;

use App\Models\Category;
use Illuminate\Support\Collection;

/**
 * Fuzzy matching engine for Arabic/English product names.
 * Normalizes Arabic characters, removes diacritics, and uses
 * Levenshtein distance + token-based comparison.
 */
class ArabicFuzzyMatcher
{
    private const MIN_FUZZY_CONFIDENCE = 65.0;
    private const HIGH_CONFIDENCE = 85.0;

    private ?Collection $cachedCategories = null;
    private array $normalizedCache = [];

    public function clearCache(): void
    {
        $this->cachedCategories = null;
        $this->normalizedCache = [];
    }

    /**
     * @return array{category: Category|null, match_type: string, confidence: float}
     */
    public function findBestMatch(string $name, ?string $sku = null, ?string $warehouse = null): array
    {
        if ($sku !== null && $sku !== '') {
            $byCode = Category::query()
                ->where('item_code', $sku)
                ->when($warehouse, fn ($q) => $q->where('warehouse', $warehouse))
                ->first();
            if ($byCode) {
                return ['category' => $byCode, 'match_type' => 'exact_sku', 'confidence' => 100.0];
            }
        }

        $categories = $this->getAllCategories($warehouse);
        $normalizedInput = $this->normalize($name);

        if ($normalizedInput === '') {
            return ['category' => null, 'match_type' => 'new', 'confidence' => 0.0];
        }

        $exactMatch = $categories->first(function (Category $cat) use ($normalizedInput) {
            return $this->getNormalized($cat) === $normalizedInput;
        });

        if ($exactMatch) {
            return ['category' => $exactMatch, 'match_type' => 'exact_name', 'confidence' => 100.0];
        }

        // No exact match found → treat as new product (no fuzzy matching)
        return ['category' => null, 'match_type' => 'new', 'confidence' => 0.0];
    }

    public static function normalize(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Remove Arabic diacritics (tashkeel)
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);

        // أ إ آ → ا
        $text = preg_replace('/[\x{0622}\x{0623}\x{0625}]/u', "\u{0627}", $text);
        // ة → ه
        $text = str_replace("\u{0629}", "\u{0647}", $text);
        // ى → ي
        $text = str_replace("\u{0649}", "\u{064A}", $text);

        // Remove parentheses, hyphens, underscores, forward slashes
        $text = preg_replace('/[()\/\-_\[\]{}]/', ' ', $text);

        // Lowercase Latin
        $text = mb_strtolower($text, 'UTF-8');

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $normalized): array
    {
        $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        // Remove "ال" prefix from Arabic tokens for comparison
        return array_map(function (string $tok) {
            if (mb_strlen($tok) > 2 && mb_substr($tok, 0, 2) === "\u{0627}\u{0644}") {
                return mb_substr($tok, 2);
            }
            return $tok;
        }, $tokens);
    }

    private function calculateSimilarity(string $normA, string $normB, ?array $tokensA = null): float
    {
        // Direct string similarity
        similar_text($normA, $normB, $strPercent);

        // Levenshtein on shorter strings (PHP limit ~255 bytes for native levenshtein)
        $levScore = 0.0;
        $mbLenA = mb_strlen($normA);
        $mbLenB = mb_strlen($normB);
        $maxLen = max($mbLenA, $mbLenB);

        if ($maxLen > 0 && $maxLen <= 255) {
            $dist = levenshtein($normA, $normB);
            $levScore = (1 - $dist / $maxLen) * 100;
        } elseif ($maxLen > 255) {
            $subA = mb_substr($normA, 0, 200);
            $subB = mb_substr($normB, 0, 200);
            $dist = levenshtein($subA, $subB);
            $levScore = (1 - $dist / max(mb_strlen($subA), mb_strlen($subB))) * 100;
        }

        // Token overlap
        $tokensA = $tokensA ?? $this->tokenize($normA);
        $tokensB = $this->tokenize($normB);
        $tokenScore = $this->tokenOverlapScore($tokensA, $tokensB);

        // Weighted average
        return ($strPercent * 0.3) + ($levScore * 0.3) + ($tokenScore * 0.4);
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    private function tokenOverlapScore(array $a, array $b): float
    {
        if (empty($a) || empty($b)) {
            return 0.0;
        }

        $matches = 0;
        $usedB = [];
        foreach ($a as $tokA) {
            foreach ($b as $idxB => $tokB) {
                if (isset($usedB[$idxB])) {
                    continue;
                }
                if ($tokA === $tokB) {
                    $matches++;
                    $usedB[$idxB] = true;
                    break;
                }
                // Partial match within tokens
                $tokLen = max(mb_strlen($tokA), mb_strlen($tokB));
                if ($tokLen > 0 && $tokLen <= 255) {
                    $d = levenshtein($tokA, $tokB);
                    if ($d <= max(1, (int) ($tokLen * 0.3))) {
                        $matches += 0.7;
                        $usedB[$idxB] = true;
                        break;
                    }
                }
            }
        }

        $maxTokens = max(count($a), count($b));
        return ($matches / $maxTokens) * 100;
    }

    private function getAllCategories(?string $warehouse): Collection
    {
        if ($this->cachedCategories === null) {
            $this->cachedCategories = Category::query()
                ->select(['id', 'category_name', 'item_code', 'warehouse', 'stock_id', 'quantity', 'total_price', 'unit_price'])
                ->get();
        }

        if ($warehouse) {
            return $this->cachedCategories->filter(fn (Category $c) => $c->warehouse === $warehouse);
        }

        return $this->cachedCategories;
    }

    private function getNormalized(Category $cat): string
    {
        if (!isset($this->normalizedCache[$cat->id])) {
            $this->normalizedCache[$cat->id] = self::normalize($cat->category_name ?? '');
        }
        return $this->normalizedCache[$cat->id];
    }

    public static function isHighConfidence(float $confidence): bool
    {
        return $confidence >= self::HIGH_CONFIDENCE;
    }
}
