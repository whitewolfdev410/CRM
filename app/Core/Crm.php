<?php

namespace App\Core;

use App\Core\Exceptions\CrmSettingsDuplicatedClientKeysException;
use App\Core\Exceptions\CrmSettingsDuplicatedClientNamesException;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

class Crm
{
    /**
     * @var Container
     */
    private $app;

    /**
     * @var Config
     */
    public $config;

    /**
     * Crm user (lower case)
     *
     * @var string
     */
    protected $crmUser;

    /**
     * Client array (lower case name => [])
     *
     * @var array
     */
    protected $clients = [];

    /**
     * Initialize class properties, set crm user and clients
     *
     * @param Container $app
     *
     * @throws \Exception
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->config = $this->app->config;

        $this->loadConfig();
    }

    public function loadConfig()
    {
        $this->crmUser = $this->normalize($this->config->get('app.crm_user'));
        $this->clients = $this->getClients();
    }

    /**
     * Verify if CRM installation name (crm_user) is equal to given name
     * or to any of given names
     *
     * @param string|array $installation
     *
     * @return bool
     */
    public function is($installation)
    {
        if (!is_array($installation)) {
            $installation = [$installation];
        }

        foreach ($installation as $i) {
            if ($this->getInstallation() == $this->normalize($i)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current installation name
     *
     * @return string
     */
    public function getInstallation()
    {
        return $this->crmUser;
    }

    /**
     * Verify if client for current installation is equal to given name
     * or to any of given names. In case if $installation parameter is specified
     * current installation also has to equal to given installation
     *
     * @param string|array $name
     * @param int          $id
     * @param string|null  $installation Installation name (crm_user).
     *
     * @return bool
     */
    public function isClient($name, $id, $installation = null)
    {
        if ($installation !== null) {
            if (!$this->is($installation)) {
                return false;
            }
        }

        if (!is_array($name)) {
            $name = [$name];
        }

        foreach ($name as $n) {
            if (isset($this->clients[$this->normalize($n)]['id'])
                && $this->clients[$this->normalize($n)]['id'] == $id
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get value for key for given client (if key is not set return null)
     *
     * @param string      $name
     * @param string      $key
     * @param null|string $installation
     *
     * @return mixed
     */
    public function getClientValue($name, $key, $installation = null)
    {
        if ($installation !== null) {
            if (!$this->is($installation)) {
                return null;
            }
        }

        $name = $this->normalize($name);
        $key = $this->normalize($key);

        if (isset($this->clients[$name][$key])) {
            return $this->clients[$name][$key];
        }

        return null;
    }

    /**
     * Get id for given client
     *
     * @param string      $name
     * @param string|null $installation
     *
     * @return mixed
     */
    public function getClientId($name, $installation = null)
    {
        return $this->getClientValue($name, 'id', $installation);
    }

    /**
     * Get clients array from configuration file
     *
     * @return array
     * @throws Exception
     */
    protected function getClients()
    {
        $clients = [];
        $configClients = $this->config->get('crm_settings.clients');
        foreach ($configClients as $name => $data) {
            $name = $this->normalize($name);
            if (isset($clients[$name])) {
                throw $this->app->make(CrmSettingsDuplicatedClientNamesException::class);
            }

            $modData = [];
            foreach ($data as $k => $val) {
                $k = $this->normalize($k);
                if (isset($modData[$k])) {
                    throw $this->app->make(CrmSettingsDuplicatedClientKeysException::class);
                }
                $modData[$k] = $val;
            }

            $clients[$name] = $modData;
        }

        return $clients;
    }

    /**
     * Normalize given value
     *
     * @param string $value
     *
     * @return string
     */
    protected function normalize($value)
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Get any setting from crm_settings file
     *
     * @param $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->config->get('crm_settings.' . $key, null);
    }

    /**
     * Verifies if application timezone is set to UTC
     *
     * @return bool
     */
    public function isUtc()
    {
        return $this->config->get('app.timezone') == 'UTC';
    }
}
