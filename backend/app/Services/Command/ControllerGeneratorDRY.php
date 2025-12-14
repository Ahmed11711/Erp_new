<?php

namespace App\Services\Command;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ControllerGeneratorDRY
{
    public static function make(string $model, ?string $module = null)
    {
         if ($module) {
            $basePath = base_path("Modules/{$module}/app/Http/Controllers/Admin/{$model}");
            $namespaceBase = "Modules\\{$module}\\Http\\Controllers\\Admin\\{$model}";
            $repositoryInterface = "Modules\\{$module}\\Repositories\\{$model}\\{$model}RepositoryInterface";
            $baseController = "Modules\\{$module}\\Http\\Controllers\\BaseEmployeeController\\BaseEmployeeController";
            $storeRequestClass = "Modules\\{$module}\\Http\\Requests\\Admin\\{$model}\\{$model}StoreRequest";
            $updateRequestClass = "Modules\\{$module}\\Http\\Requests\\Admin\\{$model}\\{$model}UpdateRequest";
            $resourceClass = "Modules\\{$module}\\Transformers\\Admin\\{$model}\\{$model}Resource";
            $initPath = strtolower($module) . "/admin/" . strtolower($model);
            $table = Str::snake(Str::pluralStudly($model));
        } else {
            $basePath = app_path("Http/Controllers/V2/{$model}");
            $namespaceBase = "App\\Http\\Controllers\\V2\\{$model}";
            $repositoryInterface = "App\\Repositories\\{$model}\\{$model}RepositoryInterface";
            $baseController = "App\\Http\\Controllers\\BaseController\\BaseController";
            $storeRequestClass = "App\\Http\\Requests\\V2\\{$model}\\{$model}StoreRequest";
            $updateRequestClass = "App\\Http\\Requests\\V2\\{$model}\\{$model}UpdateRequest";
            $resourceClass = "App\\Http\\Resources\\V2\\{$model}\\{$model}Resource";
            $initPath = "admin/" . strtolower($model);
            $table = Str::snake(Str::pluralStudly($model));
        }

        $controllerPath = $basePath . "/{$model}Controller.php";

         if (!File::isDirectory($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

         if (File::exists($controllerPath)) {
            return "⚠️ {$model}Controller already exists!";
        }

         $fileFields = [];
        if (Schema::hasTable($table)) {
            $columns = Schema::getColumnListing($table);
            foreach ($columns as $col) {
                if (preg_match('/(image|img|file|attachment|photo|picture)/i', $col)) {
                    $fileFields[] = $col;
                }
            }
        }

        $fileFieldsString = empty($fileFields)
            ? ''
            : ",\n            fileFields: ['" . implode("', '", $fileFields) . "']";

         $controllerStub = "<?php

namespace {$namespaceBase};

use {$repositoryInterface};
use {$baseController};
use {$storeRequestClass};
use {$updateRequestClass};
use {$resourceClass};

class {$model}Controller extends " . basename(str_replace('\\', '/', $baseController)) . "
{
    public function __construct({$model}RepositoryInterface \$repository)
    {
        parent::__construct();

        \$this->initService(
            repository: \$repository,
            collectionName: '{$model}'{$fileFieldsString}
        );

        \$this->storeRequestClass = {$model}StoreRequest::class;
        \$this->updateRequestClass = {$model}UpdateRequest::class;
        \$this->resourceClass = {$model}Resource::class;
    }
}
";

        File::put($controllerPath, $controllerStub);

        return "✅ {$model}Controller created successfully in {$basePath}";
    }
}
