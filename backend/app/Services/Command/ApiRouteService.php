<?php

namespace App\Services\Command;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ApiRouteService
{
    public static function make(string $model, ?string $module = null, ?string $prefix = null)
    {
         if ($module) {
             $apiFile = base_path("Modules/{$module}/routes/api.php");
        } else {
             $apiFile = base_path("routes/api.php");
        }

         if (!File::exists($apiFile)) {
            $content = "<?php\n\nuse Illuminate\Support\Facades\Route;\n\n";
            File::put($apiFile, $content);
        }

        $content = File::get($apiFile);

         $plural = Str::plural(Str::snake($model)); // ex: User -> users
        $name   = Str::snake($model);              // ex: User -> user

         $controllerNamespace = $module
            ? "Modules\\{$module}\\Http\\Controllers\\{$model}\\{$model}Controller"
            : "App\\Http\\Controllers\\Admin\\{$model}\\{$model}Controller";

        $controllerUse = "use {$controllerNamespace};";

         if (!str_contains($content, $controllerUse)) {
            $content = preg_replace(
                '/(use Illuminate\\\\Support\\\\Facades\\\\Route;)/',
                "$1\n{$controllerUse}",
                $content,
                1
            );
        }

         $routeLine = "Route::apiResource('{$plural}', {$model}Controller::class)->names('{$name}');";

         $prefixPath = $prefix ? "v2/{$prefix}" : "v2";

         $groupPattern = "/Route::prefix\('{$prefixPath}'\)->group\(function\s*\(\)\s*\{([\s\S]*?)\}\);/";

        if (preg_match($groupPattern, $content, $matches)) {
            if (!str_contains($matches[1], $routeLine)) {
                 $newGroup = str_replace(
                    "});",
                    "    {$routeLine}\n});",
                    $matches[0]
                );
                $content = str_replace($matches[0], $newGroup, $content);
            }
        } else {
             $content .= "\nRoute::prefix('{$prefixPath}')->group(function () {\n";
            $content .= "    {$routeLine}\n";
            $content .= "});\n";
        }

        File::put($apiFile, $content);

        return "✅ Route for {$model} added successfully in " . ($module ? "Modules/{$module}/routes/api.php" : "routes/api.php");
    }
}
