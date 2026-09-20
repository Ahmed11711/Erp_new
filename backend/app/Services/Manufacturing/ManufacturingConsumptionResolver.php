<?php

namespace App\Services\Manufacturing;

use App\Models\Color;
use App\Models\Item;
use App\Support\ArabicTextNormalizer;

/**
 * During manufacturing, map a BOM line to the concrete stock row to issue.
 *
 * - Non color-following materials (e.g. دمور, fasteners): always issue the base/master SKU.
 * - Color-following master items (e.g. fabrics): issue the child row whose color_id
 *   matches the finished product / production run color.
 */
final class ManufacturingConsumptionResolver
{
    /**
     * Base item used to attach recipes & Manufacture headers (one BOM per base finished good).
     * Handles: (a) properly linked variants, (b) legacy unlinked items named "Base - Color".
     */
    public static function outputAnchor(Item $output): Item
    {
        if ($output->parent_item_id) {
            return Item::query()->findOrFail((int) $output->parent_item_id);
        }

        // If item has no parent but also no Manufacture → might be a legacy colored variant
        // Try to find the base by stripping " - Color" or " — Color" from the name
        if (! \App\Models\Manufacture::where('product_id', $output->id)->exists()) {
            $name = trim((string) ($output->category_name ?? ''));
            $warehouse = (string) ($output->warehouse ?? '');

            foreach ([' - ', ' — '] as $sep) {
                $pos = mb_strrpos($name, $sep);
                if ($pos !== false) {
                    $baseName = mb_substr($name, 0, $pos);
                    $base = Item::query()
                        ->where('warehouse', $warehouse)
                        ->whereRaw('LOWER(TRIM(category_name)) = ?', [mb_strtolower(trim($baseName))])
                        ->first();
                    if ($base && \App\Models\Manufacture::where('product_id', $base->id)->exists()) {
                        return $base;
                    }
                }
            }
        }

        return $output;
    }

    public static function outputAnchorId(Item $output): int
    {
        return (int) self::outputAnchor($output)->id;
    }

    /**
     * @throws \InvalidArgumentException when a color-following line has no matching variant
     */
    public function resolveForProduction(Item $bomLineItem, ?int $productionColorId): Item
    {
        $baseId = $bomLineItem->parent_item_id
            ? (int) $bomLineItem->parent_item_id
            : (int) $bomLineItem->id;

        /** @var Item $base */
        $base = Item::query()->findOrFail($baseId);

        $followsColor = SupportsColorEstimator::followsProductionColor(
            (string) ($base->category_name ?? ''),
            (bool) $base->supports_color
        );

        if (! $followsColor) {
            return $base;
        }

        if ($productionColorId === null) {
            return $base;
        }

        $child = Item::query()
            ->where('parent_item_id', $base->id)
            ->where('color_id', $productionColorId)
            ->first();

        if ($child) {
            return $child;
        }

        $color = Color::query()->find($productionColorId);
        if ($color) {
            $needle = ArabicTextNormalizer::normalize($color->name);
            $legacy = Item::query()
                ->where('parent_item_id', $base->id)
                ->whereNotNull('color')
                ->get();

            foreach ($legacy as $row) {
                $raw = (string) ($row->color ?? '');
                if ($raw !== '' && ArabicTextNormalizer::normalize($raw) === $needle) {
                    return $row;
                }
            }
        }

        $baseName = (string) $base->category_name;
        $colorName = $color ? $color->name : "#{$productionColorId}";
        throw new \InvalidArgumentException(
            "لا يوجد صنف خام ملوّن للون «{$colorName}» تحت الأصل «{$baseName}» (ID {$base->id})."
        );
    }
}
