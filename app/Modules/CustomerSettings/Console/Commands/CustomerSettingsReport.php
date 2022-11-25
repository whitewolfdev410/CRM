<?php

namespace App\Modules\CustomerSettings\Console\Commands;

use App\Console\SingleInstanceCommand;
use App\Modules\CustomerSettings\Services\CustomerSettingsReport as Generator;
use Illuminate\Contracts\Container\Container;

class CustomerSettingsReport extends SingleInstanceCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'customer-settings:report {--send-mail}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Customer Settings Report';


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
        $sendMail = false;
        if ($this->option('send-mail')) {
            $sendMail = true;
        }

        $generator = $this->app[Generator::class];
        $generator->setOutput($this);
        $generator->generateReport($sendMail);
    }
}
