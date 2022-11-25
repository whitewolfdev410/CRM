<?php

namespace App\Core\Exceptions;

use App\Core\ErrorCodes;

class CrmSettingsDuplicatedClientKeysException extends ApiException
{
    protected $level = self::LEVEL_ALERT;
    
    public function getStatusCode()
    {
        return 500;
    }

    public function getApiCode()
    {
        return ErrorCodes::CRM_SETTING_DUPLICATED_CLIENT_KEYS;
    }

    public function getApiMessage()
    {
        return $this->trans->get('not_configured_error');
    }

    public function getDevMessage()
    {
        return 'Duplicated client keys in crm_settings file (crm_settings.clients). This file should be configured properly';
    }
}
