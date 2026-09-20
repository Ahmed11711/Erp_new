<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixCategoriesCategoryImageColumnCommand extends Command
{
    protected $signature = 'shopify:fix-category-image-column';

    protected $description = 'Make categories.category_image nullable with empty default (Shopify item create fix)';

    public function handle(): int
    {
        if (! Schema::hasColumn('categories', 'category_image')) {
            $this->error('Column categories.category_image does not exist.');

            return self::FAILURE;
        }

        DB::statement("ALTER TABLE categories MODIFY category_image VARCHAR(255) NULL DEFAULT ''");
        $this->info('categories.category_image is now NULL DEFAULT empty string.');

        return self::SUCCESS;
    }
}
