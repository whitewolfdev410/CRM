<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;
use App\Core\Trans;
use Illuminate\Container\Container;

class SendExternalNoteException extends ApiException
{
    protected $level = self::LEVEL_ERROR;
    protected $errorMessage;

    /**
     * Constructor
     * @param Container $app
     * @param Trans     $trans
     * @param string    $errorMessage
     */
    public function __construct(Container $app, Trans $trans, $errorMessage)
    {
        parent::__construct($app, $trans);
        $this->errorMessage = $errorMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return 422;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiCode()
    {
        return ErrorCodes::GENERAL_VALIDATION_ERROR;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiMessage()
    {
        return $this->errorMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function getDevMessage()
    {
        return $this->getApiMessage();
    }
}
