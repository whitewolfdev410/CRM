<?php

namespace App\Core\Extended;

use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Foundation\Bootstrap\LoadConfiguration as BaseLoadConfiguration;

class LoadConfiguration extends BaseLoadConfiguration
{
    /**
     * @inheritdoc
     */
    protected function getConfigurationFiles(Application $app)
    {
        $files = [];
        foreach (Finder::create()->files()->depth('== 0')->name('*.php')->in([
            $app->configPath(),
            $app->configPath() . DIRECTORY_SEPARATOR . $app->environment(),
        ]) as $file) {
            $files[basename($file->getRealPath(), '.php')][]
                = $file->getRealPath();
        }

        return $files;
    }

    /**
     * @inheritdoc
     */
    protected function loadConfigurationFiles(
        Application $app,
        RepositoryContract $config
    ) {
        foreach ($this->getConfigurationFiles($app) as $key => $path) {
            $settings = [];

            foreach ($path as $p) {
                $settings = array_replace_recursive($settings, require $p);
            }

            $config->set($key, $settings);
        }
    }
}
