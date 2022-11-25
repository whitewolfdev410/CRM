<?php

namespace App\Console\Commands;

use App\Services\ModuleGeneratorService;
use Exception;
use Illuminate\Console\Command;

class ModuleMakeMigration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:make-migration {module : Name of module in which you want to create migration} {name : Full name of migration example: create_sample_table} {table : Database table for which this migration is created}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates migration in selected module.';

    /**
     * @var ModuleGeneratorService
     */
    private $service;

    /**
     * Create a new command instance.
     *
     * @param ModuleGeneratorService $service
     */
    public function __construct(ModuleGeneratorService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            $module = $this->argument('module');
            $migration = $this->argument('name');
            $table = $this->argument('table');
            [$moduleName, $migrationName] =
                $this->service->generateMigration($module, $migration, $table);

            $this->info('Migration ' . $migrationName .
                " was successfully generated in '" . $moduleName . "' module");
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
