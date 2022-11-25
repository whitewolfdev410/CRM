<?php

namespace App\Modules\Address\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class VerifyAddressSendEmailException extends ApiException
{
    protected $level = self::LEVEL_WARNING;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::ADDRESS_VERIFY_SEND_EMAIL_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'There was a problem with sending e-mail with invalid states for addresses. This task will be repeated';
    }
}
