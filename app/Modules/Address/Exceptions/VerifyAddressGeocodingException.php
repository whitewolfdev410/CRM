<?php

namespace App\Modules\Address\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class VerifyAddressGeocodingException extends ApiException
{
    protected $level = self::LEVEL_WARNING;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::ADDRESS_VERIFY_ADDRESS_GEOCODING_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return "There was a problem with geocoding given address during address verification (probably address is invalid.). This task won't be automatically repeated";
    }
}
