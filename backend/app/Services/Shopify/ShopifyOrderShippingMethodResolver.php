<?php

namespace App\Services\Shopify;

use App\Enums\ShippingSizeTier;
use App\Models\Category;
use App\Models\ShippingMethod;
use App\Support\ArabicTextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShopifyOrderShippingMethodResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $lineRows
     */
    public function resolveShippingMethodId(array $lineRows, int $fallbackMethodId): int
    {
        $tier = $this->resolveOrderTier($lineRows);
        $methodId = $this->shippingMethodIdForTier($tier);

        if ($methodId === null) {
            Log::warning('Shopify shipping tier resolved but shipping_methods row missing', [
                'tier' => $tier->value,
                'fallback_method_id' => $fallbackMethodId,
            ]);

            return $fallbackMethodId;
        }

        Log::debug('Shopify order shipping method resolved', [
            'tier' => $tier->value,
            'shipping_method_id' => $methodId,
            'line_count' => count($lineRows),
        ]);

        return $methodId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineRows
     */
    public function resolveOrderTier(array $lineRows): ShippingSizeTier
    {
        $categoryIds = array_values(array_unique(array_filter(array_map(
            fn (array $row) => isset($row['category_id']) ? (int) $row['category_id'] : 0,
            $lineRows
        ))));

        $categories = $categoryIds === []
            ? collect()
            : Category::query()
                ->whereIn('id', $categoryIds)
                ->get(['id', 'category_name', 'warehouse', 'shipping_size_tier'])
                ->keyBy('id');

        $tiers = [];
        foreach ($lineRows as $row) {
            $categoryId = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            $category = $categories->get($categoryId);
            $lineLabel = (string) ($row['special_details'] ?? '');

            $tiers[] = $this->resolveLineTier($category, $lineLabel);
        }

        return ShippingSizeTier::maxOf($tiers);
    }

    private function resolveLineTier(?Category $category, string $lineLabel): ShippingSizeTier
    {
        if ($category !== null) {
            $explicit = ShippingSizeTier::tryFromString($category->shipping_size_tier);
            if ($explicit !== null) {
                return $explicit;
            }
        }

        $texts = array_filter([
            $lineLabel,
            $category?->category_name,
            $category?->warehouse,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        foreach ($texts as $text) {
            $fromKeywords = $this->matchTierFromKeywords($text);
            if ($fromKeywords !== null) {
                return $fromKeywords;
            }
        }

        if ($category !== null && is_string($category->warehouse) && trim($category->warehouse) !== '') {
            $fromWarehouse = $this->matchTierFromWarehouse($category->warehouse);
            if ($fromWarehouse !== null) {
                return $fromWarehouse;
            }
        }

        return ShippingSizeTier::tryFromString((string) config('shopify_shipping_tiers.default_tier', 'large'))
            ?? ShippingSizeTier::Large;
    }

    private function matchTierFromKeywords(string $text): ?ShippingSizeTier
    {
        $normalizedHaystack = ArabicTextNormalizer::normalize($text);
        if ($normalizedHaystack === '') {
            return null;
        }

        $rules = config('shopify_shipping_tiers.keyword_rules', []);
        if (! is_array($rules)) {
            return null;
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $tier = ShippingSizeTier::tryFromString($rule['tier'] ?? null);
            if ($tier === null) {
                continue;
            }
            $keywords = $rule['keywords'] ?? [];
            if (! is_array($keywords)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (! is_string($keyword) || trim($keyword) === '') {
                    continue;
                }
                $needle = ArabicTextNormalizer::normalize($keyword);
                if ($needle !== '' && str_contains($normalizedHaystack, $needle)) {
                    return $tier;
                }
            }
        }

        return null;
    }

    private function matchTierFromWarehouse(string $warehouse): ?ShippingSizeTier
    {
        $normalized = ArabicTextNormalizer::normalize($warehouse);
        if ($normalized === '') {
            return null;
        }

        $map = config('shopify_shipping_tiers.warehouse_tiers', []);
        if (! is_array($map)) {
            return null;
        }

        foreach ([ShippingSizeTier::Medium, ShippingSizeTier::Small] as $tier) {
            $keywords = $map[$tier->value] ?? [];
            if (! is_array($keywords)) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (! is_string($keyword) || trim($keyword) === '') {
                    continue;
                }
                $needle = ArabicTextNormalizer::normalize($keyword);
                if ($needle !== '' && str_contains($normalized, $needle)) {
                    return $tier;
                }
            }
        }

        return null;
    }

    private function shippingMethodIdForTier(ShippingSizeTier $tier): ?int
    {
        $names = config('shopify_shipping_tiers.method_names', []);
        $name = is_array($names) ? ($names[$tier->value] ?? null) : null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return Cache::remember(
            'shopify:shipping_method_id:'.mb_strtolower(trim($name)),
            300,
            fn () => ShippingMethod::query()->where('name', trim($name))->value('id')
        );
    }
}
