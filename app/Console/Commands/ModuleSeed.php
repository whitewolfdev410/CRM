<?php

namespace App\Console\Commands;

use App\Services\ModuleGeneratorService;
use Exception;
use Illuminate\Console\Command;

class ModuleSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:seed  {module : Name of module from which you want to launch seeder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launches main seeder from module.';

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

            $class = $this->service->getModuleSeeder($name);

            $result = $this->call('db:seed', [
                '--class' => $class,
                '--env' => $env,
            ]);

            if ($result == 0) {
                $this->info('Seeder ' . $class . ' has been launched');
            } else {
                $this->error('There was a problem with running seeder '.$class);
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
