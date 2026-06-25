<?php

namespace App\Services\Items;

final class RecipeSheetItemsImportResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $skipped,
        public array $warnings = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
