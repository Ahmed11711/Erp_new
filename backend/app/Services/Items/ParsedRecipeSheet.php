<?php

namespace App\Services\Items;

/**
 * DTO returned by RecipeSheetImportService::parse().
 *
 * Safe to serialize and cache between preview (step 1) and confirm (step 2)
 * of the interactive Excel import flow.
 */
class ParsedRecipeSheet
{
    /**
     * @param  array<int, array<string,mixed>>  $recipes
     * @param  array<int, string>  $missingItems
     * @param  array<int, string>  $existingRecipes
     */
    public function __construct(
        public array $recipes,
        public array $missingItems,
        public array $existingRecipes,
    ) {
    }

    public function toArray(): array
    {
        return [
            'recipes' => $this->recipes,
            'missing_items' => $this->missingItems,
            'existing_recipes' => $this->existingRecipes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            recipes: $data['recipes'] ?? [],
            missingItems: $data['missing_items'] ?? [],
            existingRecipes: $data['existing_recipes'] ?? [],
        );
    }
}
