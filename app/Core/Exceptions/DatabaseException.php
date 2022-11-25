<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class DatabaseException extends ApiException
{
    protected $level = self::LEVEL_EMERGENCY;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::DATABASE_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('database_error');
    }

    public function getDevMessage()
    {
        return 'Database is probably down or there is a problem with accessing database. Please verify database connection or contact Support team';
    }
}
