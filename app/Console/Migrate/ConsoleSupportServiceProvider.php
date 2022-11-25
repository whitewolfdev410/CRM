<?php

namespace App\Console\Migrate;

use App\Console\Migrate\MigrationServiceProvider;
use Illuminate\Foundation\Providers\ArtisanServiceProvider;
use Illuminate\Foundation\Providers\ConsoleSupportServiceProvider as BaseConsoleSupportServiceProvider;
use Illuminate\Foundation\Providers\ComposerServiceProvider;
use Illuminate\Support\AggregateServiceProvider;

class ConsoleSupportServiceProvider extends BaseConsoleSupportServiceProvider
{
    /**
     * The provider class names.
     *
     * @var array
     */
    protected $providers
        = [
            ArtisanServiceProvider::class,
            MigrationServiceProvider::class,
            ComposerServiceProvider::class
        ];
}
