<?php

namespace App\Services\Manufacturing;

use App\Models\Color;
use App\Support\ArabicTextNormalizer;
use Illuminate\Support\Str;

/**
 * Canonical Color rows for FK-based manufacturing (finished shade → raw variant).
 */
final class ColorCatalogService
{
    /**
     * @param  array<int, string>  $labels
     * @return array<int, Color>
     */
    public function resolveOrCreateMany(array $labels): array
    {
        $out = [];
        foreach ($labels as $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $out[] = $this->firstOrCreateFromLabel($label);
        }

        return $out;
    }

    public function firstOrCreateFromLabel(string $label): Color
    {
        $name = trim($label);
        $target = ArabicTextNormalizer::normalize($name);

        foreach (Color::query()->orderBy('id')->cursor() as $row) {
            if (ArabicTextNormalizer::normalize($row->name) === $target) {
                return $row;
            }
        }

        $baseSlug = Str::slug($name);
        if ($baseSlug === '') {
            $baseSlug = 'c-' . substr(sha1($name), 0, 12);
        }

        $slug = $baseSlug;
        $i = 0;
        while (Color::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . (++$i);
        }

        return Color::query()->create([
            'name' => $name,
            'slug' => $slug,
            'hex' => null,
        ]);
    }
}
