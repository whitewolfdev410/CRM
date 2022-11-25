<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class JsonEncodingException extends ApiException
{
    protected $level = self::LEVEL_EMERGENCY;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::JSON_ENCODING_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        $this->setData([
            'error' => json_last_error(),
            'message' => json_last_error_msg(),
        ]);

        return 'There was a problem with encoding data. Please contact Support team';
    }
}
