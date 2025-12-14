<?php

namespace App\Services\Command;

use Illuminate\Support\Facades\File;

class ProviderBindService
{
    public static function make(string $model)
    {
        $providerPath = app_path('Providers/AppServiceProvider.php');

        if (!File::exists($providerPath)) {
            return "AppServiceProvider not found!";
        }

        $content = File::get($providerPath);

        $interfaceNamespace  = "App\\Repositories\\{$model}\\{$model}RepositoryInterface";
        $repositoryNamespace = "App\\Repositories\\{$model}\\{$model}Repository";

        $interfaceUse  = "use {$interfaceNamespace};";
        $repositoryUse = "use {$repositoryNamespace};";

        if (!str_contains($content, $interfaceUse)) {
            $content = preg_replace(
                '/(namespace\s+App\\\\Providers;)/',
                "$1\n\n{$interfaceUse}\n{$repositoryUse}",
                $content,
                1
            );
        }

        $bindLine = "        \$this->app->bind({$model}RepositoryInterface::class, {$model}Repository::class);";

        if (!str_contains($content, $bindLine)) {
            $content = preg_replace_callback(
                '/public function register\(\): void\s*\{(.*?)\}/s',
                function ($matches) use ($bindLine) {
                    $body = trim($matches[1]);
                    if (str_contains($body, $bindLine)) {
                        return $matches[0];
                    }
                    $body .= "\n{$bindLine}";
                    return "public function register(): void {\n{$body}\n}";
                },
                $content
            );
        }

        File::put($providerPath, $content);

        return "Bind for {$model}Repository added successfully in AppServiceProvider.";
    }
}
