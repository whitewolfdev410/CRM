<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class CrmSettingsDuplicatedClientNamesException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::CRM_SETTING_DUPLICATED_CLIENT_NAMES;
    }

    public function getApiMessage()
    {
        return $this->trans->get('not_configured_error');
    }

    public function getDevMessage()
    {
        return 'Duplicated client names in crm_settings file (crm_settings.clients). This file should be configured properly';
    }
}
