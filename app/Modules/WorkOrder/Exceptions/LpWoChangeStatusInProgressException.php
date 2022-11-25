<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoChangeStatusInProgressException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CHANGE_STATUS_IN_PROGRESS_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.currently_in_progress');
    }

    public function getDevMessage()
    {
        return 'Link person WO status is in progress. Status of this Link person WO cannot be changed';
    }
}
