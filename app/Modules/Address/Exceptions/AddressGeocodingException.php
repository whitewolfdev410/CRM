<?php

namespace App\Modules\Address\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class AddressGeocodingException extends ApiException
{
    protected $level = self::LEVEL_WARNING;
    
    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::ADDRESS_GEOCODING_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return 'There was a problem with geocoding given address during geocoding (probably address is invalid.). This task might not be repeated';
    }
}
