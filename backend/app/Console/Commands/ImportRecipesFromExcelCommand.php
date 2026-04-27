<?php

namespace App\Console\Commands;

use App\Services\Items\RecipeExcelImportService;
use Illuminate\Console\Command;

class ImportRecipesFromExcelCommand extends Command
{
    protected $signature = 'items:import-recipes
        {file : Path to .xlsx / .xls / .csv}
        {--sheet= : Sheet name (optional; defaults to first sheet)}
        {--continue-on-error : Log row errors and continue (default: stop on first error)}';

    protected $description = 'Import items (categories), recipes, and BOM lines from Excel (insert/update only).';

    public function handle(RecipeExcelImportService $import): int
    {
        $path = $this->argument('file');
        if (! str_starts_with($path, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:\\\\/', $path)) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            $this->error('File not found or not readable: '.$path);

            return self::FAILURE;
        }

        $stopOnError = ! $this->option('continue-on-error');

        try {
            $result = $import->import($path, $this->option('sheet') ?: null, $stopOnError);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Rows processed: '.$result->rowsProcessed);
        $this->info('Items created: '.$result->itemsCreated);
        $this->info('Items updated (recipe link / fields): '.$result->itemsUpdated);
        $this->info('Recipes created: '.$result->recipesCreated);
        $this->info('Recipes updated: '.$result->recipesUpdated);
        $this->info('Ingredient lines upserted: '.$result->ingredientsUpserted);

        foreach ($result->warnings as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
