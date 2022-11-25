<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class JobAlreadyQueuedException extends ApiException
{
    protected $level = self::LEVEL_WARNING;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::JOB_ALREADY_QUEUED;
    }

    public function getApiMessage()
    {
        return $this->trans->get('job_already_queued');
    }

    public function getDevMessage()
    {
        return 'Job with the same name is already queued.';
    }
}
