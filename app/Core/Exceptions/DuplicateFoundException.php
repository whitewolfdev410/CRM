<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class DuplicateFoundException extends ApiException
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
        return ErrorCodes::IMPORTER_DUPLICATE_FOUND;
    }

    public function getApiMessage()
    {
        return $this->trans->get('file_duplicate_found');
    }

    public function getDevMessage()
    {
        return "Uploaded file can cause duplicate of record.(".$this->row.")";
    }
}
