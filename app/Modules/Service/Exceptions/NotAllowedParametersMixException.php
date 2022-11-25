<?php

namespace App\Modules\Service\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class NotAllowedParametersMixException extends ApiException
{
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::SERVICE_NOT_ALLOWED_PARAMETERS_MIX_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'Used parameters that are not allowed to be used together';
    }
}
