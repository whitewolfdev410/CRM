<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoMissingEcdException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_MISSING_EXPECTED_COMPLETION_DATE;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo.missing_ecd');
    }

    public function getDevMessage()
    {
        return 'Work order for this lpwo has empty Expected completion date. It should be filled before running this action';
    }
}
