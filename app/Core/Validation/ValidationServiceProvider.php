<?php

namespace App\Core\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationServiceProvider as VSP;

class ValidationServiceProvider extends VSP
{
    /**
     * Set custom validator to validator resolver
     */
    public function boot()
    {
        $this->app['validator']->resolver(function (
            $translator,
            $data,
            $rules,
            $messages
        ) {
            return new ExtendedValidator($translator, $data, $rules, $messages);
        });

        Validator::extend('spellcheck', 'App\\Core\\Validation\\SpellcheckValidator@validate');
    }
}
