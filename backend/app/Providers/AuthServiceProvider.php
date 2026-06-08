<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Rbac\PermissionResolutionService;
use App\Services\Rbac\RegisteredSlugRegistry;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::before(function ($user, $ability) {
            if (! $user instanceof User) {
                return null;
            }

            $needle = strtolower(trim((string) $ability));
            if ($needle === '') {
                return null;
            }

            $registry = app(RegisteredSlugRegistry::class)->all();
            if (! in_array($needle, $registry, true)) {
                return null;
            }

            return app(PermissionResolutionService::class)->hasPermission($user, $needle);
        });
    }
}
