<?php

namespace App\Console\Commands;

use App\Services\ModuleGeneratorService;
use Exception;
use Illuminate\Console\Command;

class ModuleGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:generate {module : Name of module you want to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates new module structure';

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
            $moduleName = $this->service->generate($name, $this);

            $this->info('Module ' . $moduleName . ' was successfully generated');
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
