<?php

namespace App\Services\Command;

use Illuminate\Support\Facades\File;

class RepositoryGenerator
{
    public static function generate(string $model, ?string $module = null)
    {
        // ✅ تحديد المسارات و الـ namespaces حسب النظام
        if ($module) {
            // 🧩 HMVC
            $repositoryDir = base_path("Modules/{$module}/app/Repositories/{$model}");
            $namespaceBase = "Modules\\{$module}\\Repositories\\{$model}";
            $baseRepository = "App\\Repositories\\BaseRepository\\BaseRepository";
            $baseRepositoryInterface = "App\\Repositories\\BaseRepository\\BaseRepositoryInterface";
            $modelNamespace = "Modules\\{$module}\\Models\\{$model}";
        } else {
            // 🧱 MVC
            $repositoryDir = app_path("Repositories/{$model}");
            $namespaceBase = "App\\Repositories\\{$model}";
            $baseRepository = "App\\Repositories\\BaseRepository\\BaseRepository";
            $baseRepositoryInterface = "App\\Repositories\\BaseRepository\\BaseRepositoryInterface";
            $modelNamespace = "App\\Models\\{$model}";
        }

        $repositoryPath = $repositoryDir . "/{$model}Repository.php";
        $interfacePath  = $repositoryDir . "/{$model}RepositoryInterface.php";

        // ✅ إنشاء الفولدر لو مش موجود
        if (!File::isDirectory($repositoryDir)) {
            File::makeDirectory($repositoryDir, 0755, true);
        }

        // ✅ إنشاء الكلاس الأساسي
        if (!File::exists($repositoryPath)) {
            $repositoryStub = "<?php

namespace {$namespaceBase};

use {$namespaceBase}\\{$model}RepositoryInterface;
use {$baseRepository};
use {$modelNamespace};

class {$model}Repository extends BaseRepository implements {$model}RepositoryInterface
{
    public function __construct({$model} \$model)
    {
        parent::__construct(\$model);
    }
}
";
            File::put($repositoryPath, $repositoryStub);
        }

        // ✅ إنشاء الـ Interface
        if (!File::exists($interfacePath)) {
            $interfaceStub = "<?php

namespace {$namespaceBase};

use {$baseRepositoryInterface};

interface {$model}RepositoryInterface extends BaseRepositoryInterface
{
    //
}
";
            File::put($interfacePath, $interfaceStub);
        }

        return "✅ {$model}Repository and Interface created successfully in " . ($module ? "Modules/{$module}/app/Repositories/{$model}" : "app/Repositories/{$model}");
    }
}
