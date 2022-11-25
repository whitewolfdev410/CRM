<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class QueryBindingException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::CANNOT_BIND_SQL_QUERY;
    }

    public function getApiMessage()
    {
        return $this->trans->get('database_error');
    }

    public function getDevMessage()
    {
        return "There was a problem with binding SQL when putting to SQL log. It's nothing serious - exception was caught but in SQL log you won't see full SQL query.";
    }
}
