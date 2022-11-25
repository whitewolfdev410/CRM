<?php

namespace App\Services;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Container\Container;
use Exception;
use Illuminate\Support\Str;

class ModuleGeneratorService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * Modules path
     *
     * @var string
     */
    protected $modulesPath;

    /**
     * Stubs path
     *
     * @var string
     */
    protected $stubsPath;

    /**
     * Namespace for new modules
     *
     * @var string
     */
    protected $namespace = 'App\\Modules\\';


    /**
     * Initialize class and set paths
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->modulesPath = app_path('Modules');
        $this->stubsPath =
            base_path('resources' . DIRECTORY_SEPARATOR . 'stubs');
    }

    /**
     * Generate new module
     *
     * @param string $module
     *
     * @return string
     * @throws Exception
     */
    public function generate($module)
    {
        $module = $this->getModuleName($module);

        $this->verifyIfModuleExists($module);

        $this->createModule($module);

        return $module;
    }

    /**
     * Generate new module in existing module
     *
     * @param string $module
     * @param string $subModule
     *
     * @return array
     * @throws Exception
     */
    public function generateSubmodule($module, $subModule)
    {
        $module = $this->getModuleName($module);
        $subModule = $this->getModuleName($subModule);

        // verify if main module exists
        $exists = $this->verifyIfModuleExists($module, true);
        if (!$exists) {
            throw new Exception("Module '" . $module .
                "' does not exist. Cannot create module inside it");
        }

        // verify if submodule already exists
        $this->verifyIfSubmoduleExists($module, $subModule);

        $this->createSubModule($module, $subModule);

        return [$module, $subModule];
    }

    /**
     * Generate new migration in select module
     *
     * @param string $module
     * @param string $migration
     * @param string $table
     *
     * @return array
     * @throws Exception
     */
    public function generateMigration($module, $migration, $table)
    {
        $module = $this->getModuleName($module);

        // verify if main module exists
        $exists = $this->verifyIfModuleExists($module, true);
        if (!$exists) {
            throw new Exception("Module '" . $module .
                "' does not exist. Cannot create migration for module that doesn't exist");
        }

        $migrationName =
            $this->createMigrationFile($module, $migration, $table);

        return [$module, $migrationName];
    }

    /**
     * Get module database migrations path (module needs to exists)
     *
     * @param string $module
     *
     * @return string
     * @throws Exception
     */
    public function getMigrationsDirectoryPath($module)
    {
        $module = $this->getModuleName($module);

        // verify if main module exists
        $exists = $this->verifyIfModuleExists($module, true);
        if (!$exists) {
            throw new Exception("Module '" . $module .
                "' does not exist. Cannot launch migrations from it");
        }

        // return relative path
        $path = $this->getMigrationsPath($module);

        return str_replace(app_path(), 'app', $path);
    }

    /**
     * Get module database seeds path (module needs to exists)
     *
     * @param string $module
     *
     * @return string
     * @throws Exception
     */
    public function getModuleSeeder($module)
    {
        $module = $this->getModuleName($module);

        // verify if main module exists
        $exists = $this->verifyIfModuleExists($module, true);
        if (!$exists) {
            throw new Exception("Module '" . $module .
                "' does not exist. Cannot launch seeds from it");
        }

        return $this->namespace . $module . '\\' .
        'Database' . '\\' . 'Seeds' . '\\' . $module . 'DatabaseSeeder';
    }

    /**
     * Verify if module exists
     *
     * @param $module
     * @param bool|false $return If set to true, no exception will be thrown
     *
     * @return bool
     * @throws Exception
     */
    protected function verifyIfModuleExists(
        $module,
        $return = false
    ) {
        $modules = $this->getModulesList();

        $name = mb_strtolower($module);

        foreach ($modules as $mod) {
            $mod = mb_strtolower($mod);
            if ($name == $mod) {
                if (!$return) {
                    throw new Exception("Module '" . $module .
                        "' already exists!");
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verify if submodule exists
     *
     * @param string $module
     * @param string $subModule
     *
     * @throws Exception
     */
    protected function verifyIfSubmoduleExists($module, $subModule)
    {
        $files = [
            $this->getSeedsPath($module, $subModule . 'DatabaseSeeder.php'),
            $this->getModelsPath($module, $subModule . '.php'),
            $this->getRepositoriesPath($module, $subModule . 'Repository.php'),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                throw new Exception("Submodule '" . $subModule .
                    "' already exists in '" . $module . "'!");
            }
        }
    }

    /**
     * Create module
     *
     * @param string $module
     *
     * @throws Exception
     */
    protected function createModule($module)
    {
        // create module main directory
        $modulePath = $this->getModulePath($module);
        if (file_exists($modulePath)) {
            throw new Exception("Files for module '" . $module .
                "' already exist!");
        }
        mkdir($modulePath);

        // create database migration directory
        mkdir($this->getMigrationsPath($module), 0777, true);

        // create database seeds directory
        $seedsPath = $this->getSeedsPath($module);
        mkdir($seedsPath, 0777, true);

        // create seeder file
        $this->createSeederFile($seedsPath, $module);

        // create controllers directory
        $controllersPath = $this->getControllersPath($module);
        mkdir($controllersPath, 0777, true);

        // create controller file
        $this->createControllerFile($controllersPath, $module);

        // create requests directory
        $requestPath = $this->getRequestsPath($module);
        mkdir($requestPath, 0777, true);

        // create request file
        $this->createRequestFile($requestPath, $module);

        // create routes file
        $this->createRoutesFile($this->getHttpPath($module), $module);

        // create models directory
        $modelPath = $this->getModelsPath($module);
        mkdir($modelPath, 0777, true);

        // create model file
        $this->createModelFile($modelPath, $module);

        // create repositories directory
        $repositoryPath = $this->getRepositoriesPath($module);
        mkdir($repositoryPath, 0777, true);

        // create repository file
        $this->createRepositoryFile($repositoryPath, $module);

        // create services directory
        $servicePath = $this->getServicesPath($module);
        mkdir($servicePath, 0777, true);

        // create service file
        $this->createServiceFile($servicePath, $module);

        // finally add new module to routes.php file
        $this->addModuleToRoutesFile($module);
    }

    /**
     * Create sub module in existing module
     *
     * @param string $module
     * @param string $subModule
     */
    protected function createSubModule($module, $subModule)
    {
        // create seeder file
        $this->createSeederFile(
            $this->getSeedsPath($module),
            $module,
            $subModule
        );

        // create model file
        $this->createModelFile(
            $this->getModelsPath($module),
            $module,
            $subModule
        );

        // create repository file
        $this->createRepositoryFile(
            $this->getRepositoriesPath($module),
            $module,
            $subModule
        );
    }

    /**
     * Create new migration in existing module from stub
     *
     * @param string $module
     * @param string $migration
     * @param string $table
     *
     * @return string
     */
    public function createMigrationFile($module, $migration, $table)
    {
        // migration file name
        $filename = date('Y_m_d_His') . '_' . Str::snake($migration) . '.php';

        // migration class name
        $migrationClass = Str::studly($migration);

        // generate new migration file
        $this->createFile(
            $module,
            $this->getMigrationsPath($module, $filename),
            'migration.stub',
            null,
            ['table' => $table, 'migrationClass' => $migrationClass]
        );

        return $filename;
    }

    /**
     * Get module path
     *
     * @param $module
     *
     * @return string
     */
    protected function getModulePath($module)
    {
        return $this->modulesPath . DIRECTORY_SEPARATOR . $module;
    }

    /**
     * Get Database seeds path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getSeedsPath($module, $file = null)
    {
        return $this->getFilePath(
            $module,
            'Database' . DIRECTORY_SEPARATOR . 'Seeds',
            $file
        );
    }

    /**
     * Get Database migrations path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getMigrationsPath($module, $file = null)
    {
        return $this->getFilePath(
            $module,
            'Database' . DIRECTORY_SEPARATOR . 'Migrations',
            $file
        );
    }

    /**
     * Get controllers path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getControllersPath($module, $file = null)
    {
        return $this->getFilePath(
            $module,
            'Http' . DIRECTORY_SEPARATOR . 'Controllers',
            $file
        );
    }

    /**
     * Get requests path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getRequestsPath($module, $file = null)
    {
        return $this->getFilePath(
            $module,
            'Http' . DIRECTORY_SEPARATOR . 'Requests',
            $file
        );
    }

    /**
     * Get Http path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getHttpPath($module, $file = null)
    {
        return $this->getFilePath($module, 'Http', $file);
    }

    /**
     * Get services path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getServicesPath($module, $file = null)
    {
        return $this->getFilePath($module, 'Services', $file);
    }

    /**
     * Get models path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getModelsPath($module, $file = null)
    {
        return $this->getFilePath($module, 'Models', $file);
    }

    /**
     * Get repositories path
     *
     * @param string $module
     * @param string|null $file
     *
     * @return string
     */
    protected function getRepositoriesPath($module, $file = null)
    {
        return $this->getFilePath($module, 'Repositories', $file);
    }

    /**
     * Get global routes.php file path (not this inside module)
     *
     * @return string
     */
    protected function getGlobalRouteFilePath()
    {
        return base_path('routes' . DIRECTORY_SEPARATOR . 'web.php');
    }

    /**
     * Get directory path (or file path)
     *
     * @param string $module
     * @param string $relativePath
     * @param string|null $file
     *
     * @return string
     */
    protected function getFilePath($module, $relativePath, $file)
    {
        $path = $this->normalizePath($this->getModulePath($module))
            . $relativePath;

        if ($file !== null) {
            $path .= DIRECTORY_SEPARATOR . $file;
        }

        return $path;
    }

    /**
     * Create controller file from stub
     *
     * @param string $path
     * @param string $module
     */
    protected function createControllerFile($path, $module)
    {
        $path = $this->normalizePath($path);

        $this->createFile(
            $module,
            $path . $module . 'Controller.php',
            'controller.stub'
        );
    }

    /**
     * Create Database seeder file from stub
     *
     * @param string $path
     * @param string $module
     * @param string|null $subModule
     */
    protected function createSeederFile($path, $module, $subModule = null)
    {
        $path = $this->normalizePath($path);

        $file = ($subModule === null) ? $module : $subModule;

        $this->createFile(
            $module,
            $path . $file . 'DatabaseSeeder.php',
            'seeder.stub',
            $subModule
        );
    }

    /**
     * Create request file from stub
     *
     * @param string $path
     * @param string $module
     */
    protected function createRequestFile($path, $module)
    {
        $path = $this->normalizePath($path);

        $this->createFile(
            $module,
            $path . $module . 'Request.php',
            'request.stub'
        );
    }

    /**
     * Create route file from stub
     *
     * @param string $path
     * @param string $module
     */
    protected function createRoutesFile($path, $module)
    {
        $path = $this->normalizePath($path);

        $this->createFile($module, $path . 'routes.php', 'routes.stub');
    }

    /**
     * Create model file from stub
     *
     * @param string $path
     * @param string $module
     * @param string|null $subModule
     */
    protected function createModelFile($path, $module, $subModule = null)
    {
        $path = $this->normalizePath($path);

        $file = ($subModule === null) ? $module : $subModule;

        $this->createFile(
            $module,
            $path . $file . '.php',
            'model.stub',
            $subModule
        );
    }

    /**
     * Create repository file from stub
     *
     * @param string $path
     * @param string $module
     * @param string|null $subModule
     */
    protected function createRepositoryFile($path, $module, $subModule = null)
    {
        $path = $this->normalizePath($path);

        $file = ($subModule === null) ? $module : $subModule;

        $this->createFile(
            $module,
            $path . $file . 'Repository.php',
            'repository.stub',
            $subModule
        );
    }

    /**
     * Create service file from stub
     *
     * @param string $path
     * @param string $module
     */
    protected function createServiceFile($path, $module)
    {
        $path = $this->normalizePath($path);

        $this->createFile(
            $module,
            $path . $module . 'Service.php',
            'service.stub'
        );
    }

    /**
     * Normalizes path (add DIRECTORY_SEPARATOR at the end if necessary)
     *
     * @param string $path
     *
     * @return string
     */
    protected function normalizePath($path)
    {
        if (!Str::endsWith($path, DIRECTORY_SEPARATOR)) {
            $path .= DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    /**
     * Create file
     *
     * @param string $module
     * @param string $fileName
     * @param string $stubName
     * @param string|null $subModule
     * @param array $extraChangers
     */
    protected function createFile(
        $module,
        $fileName,
        $stubName,
        $subModule = null,
        array $extraChangers = []
    ) {
        $changers = $this->getChangers($module, $subModule, $extraChangers);

        file_put_contents(
            $fileName,
            str_replace(
                array_keys($changers),
                array_values($changers),
                file_get_contents($this->stubsPath . DIRECTORY_SEPARATOR .
                $stubName)
            )
        );
    }

    /**
     * Get changers array that will be used to replace placeholders in stub
     * files
     *
     * @param string $module
     * @param string|null $subModule
     * @param array $extraChangers
     *
     * @return array
     */
    protected function getChangers(
        $module,
        $subModule = null,
        array $extraChangers = []
    ) {
        // set valid name (to module or submodule)
        $name = ($subModule !== null) ? $subModule : $module;

        // standard changers array
        $changers = [
            '{{module}}' => $module,
            '{{namespace}}' => $this->namespace,
            '{{name}}' => $name,
            '{{smallnameCamel}}' => lcfirst($name),
            '{{smallname}}' => mb_strtolower($name),
            '{{plural}}' => Str::plural(mb_strtolower($name)),
            '{{smallnameSnake}}' => Str::snake($name),
        ];

        // if there are any extra changers add them to changers
        if ($extraChangers) {
            foreach ($extraChangers as $k => $v) {
                $changers['{{' . $k . '}}'] = $v;
            }
        }

        return $changers;
    }

    /**
     * Get module name (in correct format)
     *
     * @param string $moduleName
     *
     * @return string
     */
    public function getModuleName($moduleName)
    {
        return Str::studly($moduleName);
    }

    /**
     * Add new module to routes.php file
     *
     * @param string $module
     * @throws Exception
     */
    protected function addModuleToRoutesFile($module)
    {
        // get module string
        $modulesString = $this->getModulesStringFromRoutesFile();

        // set replacer to module string
        $replacer = $modulesString;

        // add to replacer new module
        if (!Str::endsWith(trim($modulesString), ',')) {
            $replacer .= ',';
        }
        $replacer .= "'{$module}',\n";

        // save modified file
        file_put_contents(
            $this->getGlobalRouteFilePath(),
            str_replace(
                $modulesString,
                $replacer,
                file_get_contents($this->getGlobalRouteFilePath())
            )
        );
    }

    /**
     * Get modules string from routes.php file
     *
     * @return string
     *
     * @throws Exception
     */
    protected function getModulesStringFromRoutesFile()
    {
        // get modules string
        $routeFile = file_get_contents($this->getGlobalRouteFilePath());
        preg_match('/\$modules\s*?=\s*?\[(.*)\]/Uis', $routeFile, $matches);

        // no match - throw exception
        if (!isset($matches[1])) {
            throw new Exception("Couldn't parse routes.php file");
        }

        // return modules string
        return $matches[1];
    }

    /**
     * Get current modules list (registered in routes.php)
     *
     * @return array
     * @throws Exception
     */
    protected function getModulesList()
    {
        // get modules string
        $modulesString = $this->getModulesStringFromRoutesFile();

        $modules = [];
        $mods = explode(',', $modulesString);

        // save modules to $modules variable
        foreach ($mods as $mod) {
            $mod = trim($mod);
            if ($mod == '') {
                continue;
            }
            $modules[] = str_replace(['\'', '"'], [], $mod);
        }

        // return modules
        return $modules;
    }
}
