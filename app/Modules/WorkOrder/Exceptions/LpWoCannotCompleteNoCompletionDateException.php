<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoCannotCompleteNoCompletionDateException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_CANT_COMPLETE_NO_COMPLETION_DATE_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_complete.cant_complete_no_completion_date');
    }

    public function getDevMessage()
    {
        return 'Cannot complete. User should enter actual completion date before they change status to completed';
    }
}
