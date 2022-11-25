<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class SqlPaginatorInvalidDataPassedException extends ApiException
{
    protected $level = self::LEVEL_ALERT;

    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::SQL_PAGINATOR_INVALID_DATA_PASSED_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('general_error');
    }

    public function getDevMessage()
    {
        return "Invalid data passed to SQL paginator. 'sql' and 'columns' should be present in arguments. Please contact API team";
    }
}
