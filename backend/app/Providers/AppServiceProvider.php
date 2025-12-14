<?php

namespace App\Providers;

use App\Models\Order;
use Doctrine\DBAL\Types\Type;

use App\Observers\OrderObserver;
use App\Repositories\AccountTree\AccountTreeRepository;
use App\Repositories\AccountTree\AccountTreeRepositoryInterface;
use Doctrine\DBAL\Types\StringType;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Repositories\stock\stockRepository;
use App\Repositories\stock\stockRepositoryInterface;
use App\Repositories\TreeAccount\TreeAccountRepository;
use App\Repositories\TreeAccount\TreeAccountRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(TreeAccountRepositoryInterface::class,TreeAccountRepository::class);
        $this->app->bind(stockRepositoryInterface::class,stockRepository::class);
        $this->app->bind(AccountTreeRepositoryInterface::class,AccountTreeRepository::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        
        Schema::defaultStringLength(191);
         if (class_exists(\Doctrine\DBAL\Types\Type::class)) {
        $platform = Schema::getConnection()->getDoctrineSchemaManager()->getDatabasePlatform();
        if (! $platform->hasDoctrineTypeMappingFor('enum')) {
            $platform->registerDoctrineTypeMapping('enum', 'string');
        }

            Order::observe(OrderObserver::class);

    
    }
    }
}
