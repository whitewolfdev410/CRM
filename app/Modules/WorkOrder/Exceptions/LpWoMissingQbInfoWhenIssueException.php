<?php

namespace App\Modules\WorkOrder\Exceptions;

use App\Core\ErrorCodes;
use App\Core\Exceptions\ApiException;

class LpWoMissingQbInfoWhenIssueException extends ApiException
{
    protected $level = self::LEVEL_NOTICE;

    public function getStatusCode()
    {
        return 422;
    }

    public function getApiCode()
    {
        return ErrorCodes::LPWO_MISSING_QB_INFO_WHEN_ISSUE_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('lpwo_status_change.issue.missing_qb_info');
    }

    public function getDevMessage()
    {
        return 'Field qb_info is empty for this lpwo. User should fill it before trying again';
    }
}
