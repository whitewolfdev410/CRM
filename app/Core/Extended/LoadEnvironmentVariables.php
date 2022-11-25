<?php

namespace App\Core\Extended;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\Adapter\ServerConstAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Env;

class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     * @param  Application $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $detectedEnv = $this->detectEnvironment($app);

        $this->setEnvironmentFilePath($app, $detectedEnv);

        $this->loadDotenvFile($app);

        $this->setAppEnvironment($app, $detectedEnv);
    }

    /**
     * Try to detect current environment from --env option, APP_ENV variable or domain name
     * @param  Application $app
     * @return string|null
     */
    private function detectEnvironment(Application $app)
    {
        return $app->detectEnvironment(function () use ($app) {
            // use APP_ENV or domain
            return env('APP_ENV', $this->getWebDomainName());
        });
    }

    /**
     * Get domain name if running as web request
     * @return string
     */
    private function getWebDomainName()
    {
        $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $domain = str_replace(':', '', $domain);

        return $domain;
    }

    /**
     * Set environment dotenv file
     * @param Application $app
     * @param string|null $env
     * @return void
     */
    private function setEnvironmentFilePath(Application $app, $env)
    {
        // if '.env' file exists, it's loaded by default for any environment unless it has corresponding specific env file,
        // it's also used if you run application without --env / APP_ENV variable
        // this allows to always run the application under the same environment regardless of request domains/envs
        //
        // do not create '.env' file if the application is supposed to handle more than one live domain
        // instead, use specific env files '.env.<env-name>' - e.g., '.env.first-domain.com', '.env.second-domain.com'
        // you can create symlinks with different names to easily point multiple domains/envs to the same env file
        // > ln -s .env.foo .env.fs-foo-crm-x.com
        // - this allows you to run artisan commands with --env=foo and access web page at http://fs-foo-crm-x.com
        //
        // remember to define APP_ENV in your env files
        // it doesn't have to match env filename, but there must be folder with the same name in config/ directory - e.g.,
        // APP_ENV=foo -> crm-x/config/foo/

        $file = $app->environmentFile();
        if ($env) {
            $file .= '.' . $env;
        }

        if (file_exists($app->environmentPath() . '/' . $file)) {
            $app->loadEnvironmentFrom($file);

            return true;
        }

        return false;
    }

    /**
     * Load dotenv file
     * @param  Application $app
     * @return void
     */
    private function loadDotenvFile(Application $app)
    {
        // unset existing APP_ENV, so it can be overridden by dotenv (original value has been stored in $detectedEnv)
        $this->unsetAppEnvVariable();

        try {
            $repository = RepositoryBuilder::createWithNoAdapters()
                ->addAdapter(EnvConstAdapter::class)
                ->addWriter(ServerConstAdapter::class)
                ->immutable()
                ->make();
            
            Dotenv::create($repository, $app->environmentPath(), null)->load();
        } catch (InvalidPathException $e) {
        } catch (InvalidFileException $e) {
            die('The environment file is invalid: ' . $e->getMessage());
        } catch (\Exception $e) {
            print_r($e->getMessage());
        }
    }

    /**
     * Clear APP_ENV variable
     * @return void
     */
    private function unsetAppEnvVariable()
    {
        $name = 'APP_ENV';

        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }

    /**
     * Set final app environment from APP_ENV variable, previously detected value or default (testing)
     * @param Application $app
     * @param string $detectedEnv
     * @return void
     */
    private function setAppEnvironment(Application $app, $detectedEnv)
    {
        $env = env('APP_ENV') ?: $detectedEnv ?: 'testing';

        $app['env'] = $env;

        if (!$this->isEnvironmentConfigured($app, $env)) {
            die('This domain/environment is not configured: ' . $env);
        }

        // APP_ENV variable should always be set
        $this->setAppEnvVariable($env);
    }

    /**
     * Whether environment is configured
     * @param  Application $app
     * @param  string      $env
     * @return bool
     */
    private function isEnvironmentConfigured(Application $app, $env)
    {
        return file_exists($app['path.base'] . '/config/' . $env);
    }

    /**
     * Set APP_ENV variable
     * @param string $value
     * @return void
     */
    private function setAppEnvVariable($value)
    {
        $name = 'APP_ENV';

        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
