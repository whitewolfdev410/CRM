<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class DuplicateRecordFoundException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;
    protected $row = 0;

    public function setRow($row)
    {
        $this->row = (int)$row;
    }

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::DUPLICATE_FOUND;
    }

    public function getApiMessage()
    {
        return $this->trans->get('duplicate_record_found');
    }

    public function getDevMessage()
    {
        return "Cannot add a duplicate of record";
    }
}
