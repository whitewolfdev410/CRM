<?php

namespace App\Core\Exceptions;

use Exception;
use Illuminate\Support\Facades\App;
use App\Core\Trans;
use Illuminate\Contracts\Container\Container;

/**
 * Api exception class
 * Provides unified interface for all api exceptions.
 */
abstract class ApiException extends Exception
{
    /**
     * Array for validated fields messages
     *
     * @var array
     */
    protected $fields = [];

    /**
     * General data
     *
     * @var array
     */
    protected $data = [];

    /**
     * @var Trans
     */
    protected $trans;
    /**
     * @var Container
     */
    private $app;

    abstract public function getStatusCode();

    abstract public function getApiCode();

    abstract public function getApiMessage();

    abstract public function getDevMessage();

    // level errors - strings should match Illuminate/Log/Logger.php
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ALERT = 'alert';
    const LEVEL_EMERGENCY = 'emergency';

    /**
     * Selected level of reporting
     *
     * @var string
     */
    protected $level = self::LEVEL_ERROR;

    /**
     * @override
     * @param Trans $trans
     */
    public function __construct(Container $app, Trans $trans)
    {
        $this->trans = $trans;
        parent::__construct($this->getMessage(), $this->getCode(), null);
        $this->app = $app;
    }

    /**
     * Set validation fields (or return them if $returnOnly set to true)
     *
     * @param array $errors
     *
     * @param bool $returnOnly
     * @return ApiException|array
     */
    public function setFields(array $errors, $returnOnly = false)
    {
        $fields = [];

        foreach ($errors as $fieldName => $fieldMessages) {
            $messages = [];
            $data = [];

            foreach ($fieldMessages as $singleMessage) {
                if (isset($singleMessage['data']) &&
                    isset($singleMessage['message'])
                ) {
                    $messages[] = $singleMessage['message'];
                    $data = array_merge($data, $singleMessage['data']);
                } else {
                    $messages[] = $singleMessage;
                }
            }

            $field = (object)[
                'name' => $fieldName,
                'messages' => array_unique($messages),
                'data' => $data,
                'nested' => [],
            ];

            $fields[] = $field;
        }

        if (!$returnOnly) {
            $this->fields = $fields;

            return $this;
        } else {
            return $fields;
        }
    }

    /**
     * Return response data
     *
     * @return object
     */
    public function getResponseData()
    {
        return (object)[
            'status' => $this->getStatusCode(),
            'error' => (object)[
                'code' => (int)$this->getApiCode(),
                'message' => $this->getApiMessage(),
                'devMessage' => $this->getDevMessage(),
                'fields' => (array)$this->fields,
                'data' => (array)$this->data,
            ],
        ];
    }

    /**
     * Log exception for error
     *
     * @return bool
     */
    public function log()
    {
        //print_r((array)$this->getResponseData());
        // die();
        $this->app->log->write(
            $this->level,
            $this->getLogMessage(),
            (array)$this->getResponseData()
        );
    }

    /**
     * Set exception data that are not assigned to any fields
     *
     * @param $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get exception data (that are not assigned to any fields)
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get message that will be used for log
     *
     * @return string
     */
    protected function getLogMessage()
    {
        return 'API exception error: ' . $this->getApiCode();
    }
}
