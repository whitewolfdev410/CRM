<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;
use App\Modules\Person\Models\Person;

class LockedMismatchException extends ApiException
{
    public function getStatusCode()
    {
        return 409;
    }

    public function getApiCode()
    {
        return ErrorCodes::LOCKED_MISMATCH_ERROR;
    }

    public function getApiMessage()
    {
        return $this->trans->get('resource_locked_by_other');
    }

    public function getDevMessage()
    {
        $by = 'other person';

        if (!empty($this->data['locked_id'])) {
            try {
                $by = Person::find($this->data['locked_id'])->getName();
            } catch (\Exception $e) {
            }
        }

        return "Resource is locked by {$by}. User should try again later when this resource won't be locked by any other person";
    }
}
