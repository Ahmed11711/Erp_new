<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InfoSyncService;
use App\Services\ColumnSyncService;
use Illuminate\Support\Facades\Log;
use App\Services\ControllerGenerator;
use App\Services\ModuleSeederService;
use App\Services\RelationSyncService;
use Illuminate\Support\Facades\Artisan;
use App\Services\Command\ApiRouteService;
use App\Services\Command\RequestGenerator;
use App\Services\Command\ResourceGenerator;
use App\Services\Command\ProviderBindService;
use App\Services\Command\RepositoryGenerator;
use App\Services\Command\ControllerGeneratorDRY;

class CrudGeneratorCommand extends Command
{
protected $signature = 'crud:generate {model} ';

        protected $description = 'Generate CRUD (Controller, Requests, Resource, Repository) inside an HMVC Module';

        public function handle()
        {
                // $module = $this->argument('module');
                $model = $this->argument('model');
                // $seeder = $this->argument('seed') ?? 'True';



                 RepositoryGenerator::generate($model);
                // Generate Request Validation
                RequestGenerator::make( $model);
                  ResourceGenerator::make($model);
                // Generate Api Resource
                ApiRouteService::make( $model);
                // Generate Bind Repository
                ProviderBindService::make( $model);


                ControllerGeneratorDRY::make($model);

                $this->info("CRUD generated for {$model} inside{");

                Artisan::call('optimize');
                $this->info("Artisan optimize executed successfully.");
                $this->info('Artisan optimize executed successfully.');

                // Sync Info
                // InfoSyncService::make($module, $model);
                // RelationSyncService::make($module, $model);

                // $this->info("CRUD generated for {$model} ");

                Artisan::call('optimize');
                $this->info("Artisan optimize executed successfully.");
        }
}
