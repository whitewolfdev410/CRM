<?php

namespace App\Modules\CustomerSettings\Console\Commands;

use App\Console\SingleInstanceCommand;
use App\Modules\CustomerSettings\Services\CustomerSettingsPictureRequirements;
use Illuminate\Contracts\Container\Container;

class CreatePicturesRequirementsCustomerSettings extends SingleInstanceCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'customer-settings:import-file-link-settings
                                {path : Path to xlsx file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old link file required settings and import new using given file';

    private $app;

    /**
     * Create a new command instance.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        parent::__construct();

        $this->app = $app;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path');
        $generator = $this->app[CustomerSettingsPictureRequirements::class];
        $generator->setPath($path);
        $generator->setOutput($this);

        $generator->import();
    }
}
