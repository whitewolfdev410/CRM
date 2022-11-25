<?php

namespace App\Console\Commands;

use App\Services\ModuleGeneratorService;
use Exception;
use Illuminate\Console\Command;

class ModuleMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:migrate  {module : Name of module from which you want to launch migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launches all migrations from module.';

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
            $name = $this->argument('module');
            $env = $this->option('env');

            if (!$env) {
                throw new Exception('Please fill in valid --env option' .
                    ' with valid environment name');
            }

            $path = $this->service->getMigrationsDirectoryPath($name);

            $result = $this->call('migrate', [
                '--path' => $path,
                '--env' => $env,
            ]);

            if ($result != 0) {
                $this->error('There was a problem with running migrations from ' .
                    $path . ' directory');
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
