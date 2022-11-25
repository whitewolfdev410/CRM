<?php

namespace App\Modules\CustomerSettings\Providers;

use App\Modules\CustomerSettings\Console\Commands\CreatePicturesRequirementsCustomerSettings;
use App\Modules\CustomerSettings\Console\Commands\CustomerSettingsReport;
use App\Modules\CustomerSettings\Console\Commands\CustomerSettingsUpdate;
use Illuminate\Support\ServiceProvider;

class CustomerSettingsServiceProvider extends ServiceProvider
{
    /**
     * Register the ExternalServices module service provider.
     *
     * @return void
     */
    public function register()
    {
        // This service provider is a convenient place to register your modules
        // services in the IoC container. If you wish, you may make additional
        // methods or service providers to keep the code more focused and granular.

        $this->registerCommands();
    }

    /**
     * Bootstrap application services
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register commands
     *
     * @return void
     */
    private function registerCommands()
    {
        $this->commands([
            CustomerSettingsReport::class,
            CreatePicturesRequirementsCustomerSettings::class,
            CustomerSettingsUpdate::class
        ]);
    }
}
