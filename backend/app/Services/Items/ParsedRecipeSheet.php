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
     * @param  array<int, string>  $parsedSheetNames  Worksheet titles that contributed recipes
     */
    public function __construct(
        public array $recipes,
        public array $missingItems,
        public array $existingRecipes,
        public array $parsedSheetNames = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'recipes' => $this->recipes,
            'missing_items' => $this->missingItems,
            'existing_recipes' => $this->existingRecipes,
            'parsed_sheet_names' => $this->parsedSheetNames,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            recipes: $data['recipes'] ?? [],
            missingItems: $data['missing_items'] ?? [],
            existingRecipes: $data['existing_recipes'] ?? [],
            parsedSheetNames: $data['parsed_sheet_names'] ?? [],
        );
    }
}
