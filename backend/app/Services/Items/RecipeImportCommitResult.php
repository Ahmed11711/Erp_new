<?php

namespace App\Services\Items;

/**
 * Counters / summary returned by RecipeSheetImportService::commit().
 */
class RecipeImportCommitResult
{
    public int $itemsCreated = 0;
    /** Existing items whose color was filled from Excel (no quantity change). */
    public int $itemsUpdated = 0;
    public int $recipesCreated = 0;
    public int $recipesUpdated = 0;
    public int $recipesSkipped = 0;
    public int $ingredientsUpserted = 0;
    /** Finished-goods auto-created in مخزن منتج تام for new recipes. */
    public int $productsCreated = 0;
    /** Existing items in مخزن منتج تام back-linked to a recipe. */
    public int $productsLinked = 0;
    /** New rows in `manufactures` (so the recipe shows on /manufacturing/recipes). */
    public int $manufacturesCreated = 0;
    /** `manufactures` rows whose BOM lines we wiped + re-inserted (replace action). */
    public int $manufacturesUpdated = 0;
    /** Extra cost lines imported from Excel footer rows. */
    public int $extraCostsCreated = 0;

    /** @var array<int, array<string,mixed>> */
    public array $committedRecipes = [];

    public function toArray(): array
    {
        return [
            'items_created' => $this->itemsCreated,
            'items_updated' => $this->itemsUpdated,
            'recipes_created' => $this->recipesCreated,
            'recipes_updated' => $this->recipesUpdated,
            'recipes_skipped' => $this->recipesSkipped,
            'ingredients_upserted' => $this->ingredientsUpserted,
            'products_created' => $this->productsCreated,
            'products_linked' => $this->productsLinked,
            'manufactures_created' => $this->manufacturesCreated,
            'manufactures_updated' => $this->manufacturesUpdated,
            'extra_costs_created' => $this->extraCostsCreated,
            'committed_recipes' => $this->committedRecipes,
        ];
    }
}
