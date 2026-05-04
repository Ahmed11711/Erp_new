<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductType;
use App\Models\Item;
use App\Models\Recipe;

/**
 * Validates BOM structure: output type, ingredient types, no self-reference.
 */
final class RecipeStructureValidator
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertValidForRecipe(Recipe $recipe, ?int $outputItemId): void
    {
        if ($outputItemId === null) {
            return;
        }

        $output = Item::query()->find($outputItemId);
        if (! $output) {
            throw new \InvalidArgumentException('Output product not found.');
        }

        $outType = $output->resolvedProductType();
        if (! in_array($outType, [ProductType::SemiFinished, ProductType::Finished], true)) {
            throw new \InvalidArgumentException(
                'Recipe output must be a semi-finished or finished product (not raw material).'
            );
        }

        $recipe->loadMissing('ingredients.item');
        foreach ($recipe->ingredients as $ing) {
            $ingItem = $ing->item;
            if (! $ingItem) {
                throw new \InvalidArgumentException('Ingredient item missing for recipe line #' . $ing->id);
            }
            if ((int) $ingItem->id === (int) $outputItemId) {
                throw new \InvalidArgumentException('Recipe cannot list the output product as its own ingredient.');
            }

            $inType = $ingItem->resolvedProductType();
            if ($inType === ProductType::Finished) {
                throw new \InvalidArgumentException(
                    'Recipe ingredients must be raw materials or semi-finished items only (finished product used as ingredient: '
                    . $ingItem->category_name . ').'
                );
            }
        }
    }
}
