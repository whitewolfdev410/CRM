<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class SendFaxFailureException extends ApiException
{
    protected $level = self::LEVEL_ERROR;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_PRINT_WO_SEND_FAX_EXCEPTION;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo.work_order.send_fax.failure');
    }

    public function getDevMessage()
    {
        return 'It was impossible to send fail to FAX server or verify whether file has been uploaded to FAX server';
    }
}
