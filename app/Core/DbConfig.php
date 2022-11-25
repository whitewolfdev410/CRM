<?php

namespace App\Core;

use Illuminate\Contracts\Config\Repository as Config;

class DbConfig
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * Constructor
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->connectionName = $config->get('database.default');
    }

    /**
     * Get default connection name
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * Determine if the given configuration value exists.
     *
     * @param  string $key
     * @return bool
     */
    public function has($key)
    {
        return $this->config->has($this->getKey($key));
    }

    /**
     * Get the specified configuration value.
     *
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->config->get($this->getKey($key), $default);
    }

    /**
     * Get config key
     * @param  string $key
     * @return string
     */
    private function getKey($key)
    {
        return "database.connections.{$this->connectionName}.{$key}";
    }
}
