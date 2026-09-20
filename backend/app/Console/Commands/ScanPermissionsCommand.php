<?php

namespace App\Console\Commands;

use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ScanPermissionsCommand extends Command
{
    protected $signature = 'permissions:scan {--apply : Insert missing permissions into the database} {--guard= : Permission guard name (default: auth.defaults.guard)}';

    protected $description = 'Scan PHP/TS sources for permission slugs (middleware permission:, helpers, etc.) and optionally register them.';

    public function handle(): int
    {
        $guard = $this->option('guard') ?: config('auth.defaults.guard');

        $roots = [
            base_path('app'),
            base_path('routes'),
            base_path('resources/views'),
            base_path('../front-end/src'),
        ];

        $found = [];
        $patterns = [
            '/middleware\s*\(\s*[^\)]*permission\s*:\s*([^)\'"|\s]+)/i',
            '/has_permission\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/hasPermission\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/i',
            '/has_any_permission\s*\(\s*\[\s*([^]]+)\]/i',
        ];

        foreach ($roots as $root) {
            if (! File::isDirectory($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $path = $file->getPathname();
                if (! preg_match('/\.(php|blade\.php|ts|html)$/i', $path)) {
                    continue;
                }
                $contents = @file_get_contents($path);
                if ($contents === false) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match_all($pattern, $contents, $matches)) {
                        foreach ($matches[1] as $raw) {
                            foreach (preg_split('/[\s|,\']+/', $raw) as $piece) {
                                $piece = trim($piece, " '\"");
                                if ($piece !== '' && preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $piece)) {
                                    $found[strtolower($piece)] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        ksort($found);
        $slugs = array_keys($found);

        $this->info('Discovered '.count($slugs).' candidate permission slugs.');

        $existing = Permission::query()->where('guard_name', $guard)->pluck('slug')->map(fn ($s) => strtolower((string) $s))->all();
        $existingFlip = array_fill_keys($existing, true);

        $missing = [];
        foreach ($slugs as $slug) {
            if (! isset($existingFlip[$slug])) {
                $missing[] = $slug;
            }
        }

        if ($missing === []) {
            $this->info(sprintf('No missing permissions for guard [%s].', $guard));

            return self::SUCCESS;
        }

        $this->table(['Missing slug'], array_map(fn ($s) => [$s], $missing));

        $sqlLines = [];
        $now = now()->toDateTimeString();
        foreach ($missing as $slug) {
            $module = explode('.', $slug)[0] ?: 'general';
            $name = ucwords(str_replace(['.', '-', '_'], ' ', $slug));
            $sqlLines[] = sprintf(
                "INSERT INTO `permissions` (`name`,`guard_name`,`module`,`slug`,`description`,`created_at`,`updated_at`) VALUES ('%s','%s','%s','%s',NULL,'%s','%s');",
                addslashes($name),
                addslashes($guard),
                addslashes($module),
                addslashes($slug),
                $now,
                $now
            );
        }

        File::makeDirectory(storage_path('app'), 0755, true, true);
        $reportPath = storage_path('app/rbac_scan_permissions.sql');
        file_put_contents($reportPath, implode("\n", $sqlLines));
        $this->info('SQL written to: '.$reportPath);

        if ($this->option('apply')) {
            foreach ($missing as $slug) {
                $module = explode('.', $slug)[0] ?: 'general';
                $name = ucwords(str_replace(['.', '-', '_'], ' ', $slug));
                Permission::query()->firstOrCreate(
                    ['slug' => $slug, 'guard_name' => $guard],
                    [
                        'name' => $name,
                        'module' => $module,
                        'description' => null,
                    ]
                );
            }
            $this->info('Inserted '.count($missing).' permissions.');

            return self::SUCCESS;
        }

        $this->warn('Run with --apply to insert records.');

        return self::SUCCESS;
    }
}
