<?php

namespace App\Http\Requests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;
use App\Core\Exceptions\ValidationException;
use App\Core\InputFormatter;
use App\Core\User;
use App\Core\Validation\ExtendedValidator;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Factory;
use Illuminate\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;

abstract class Request extends FormRequest
{
    /**
     * {@inheritdoc}
     */
    protected function getValidatorInstance()
    {
        $factory = $this->container->make(Factory::class);
        $factory->resolver(function ($translator, $data, $rules, $messages, $attributes) {
            return new ExtendedValidator($translator, $data, $rules, $messages, $attributes);
        });

        if (method_exists($this, 'validator')) {
            return $this->container->call(
                [$this, 'validator'],
                compact('factory')
            );
        }

        $validator = $factory->make(
            $this->validationData(),
            $this->container->call([$this, 'rules']),
            $this->messages()
        )->setAttributeNames($this->attributes());

        return $validator;
    }

    /**
     * Get the input that should be fed to the validator.
     *
     * @return array
     */
    public function validationData()
    {
        return $this->all();
    }

    /**
     * Format the errors from the given Validator instance.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return array
     */
    protected function formatErrors(Validator $validator)
    {
        return $validator->getMessageBag()->toArray();
    }

    /**
     * Get formatter rules
     *
     * @return array
     */
    protected function getFormatterRules()
    {
        return [];
    }

    /**
     * Extended default getInputSource with formatter rules
     */
    protected function getInputSource()
    {
        $data = parent::getInputSource();
        $rules = $this->getFormatterRules();
        $formatter = new InputFormatter();

        return $formatter->format($data, $rules);
    }

    /*/**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(ValidatorContract $validator)
    {
        $validationException = App::make(ValidationException::class);
        $validationException->setFields(
            $this->formatErrors($validator)
        );

        throw $validationException;
    }

    /**
     * Make all authorized user to make a request (we don't check permission
     * here - they should be checked in controllers)
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get basic validation rules
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules();
    }

    /**
     * Returns only $allowed rules to show validation rules for frontend
     *
     * @return array
     */
    public function getFrontendRules()
    {
        $rules = $this->getRules();

        $output = [];

        $allowed = [
            'required',
            'present',
            'max:',
            'min:',
            'numeric',
            'string',
            'integer',
            'array',
            'boolean'
        ];

        foreach ($rules as $field => $values) {
            if (!is_array($values)) {
                $values = explode('|', $values);
            }
            foreach ($values as $key => $value) {
                if (!Str::startsWith(trim($value), $allowed)) {
                    unset($values[$key]);
                }
            }
            if (empty($values)) {
                unset($rules[$field]);
            } else {
                $output[$field]['rules'] = array_values($values);
            }
        }

        return $output;
    }

    /**
     * Add GPS mobile required rule in case it's set in config
     *
     * @param array $rules
     *
     * @return array
     */
    protected function addGpsMobileRequiredRule(array $rules)
    {
        $gpsRequired = config('mobile.settings.actions_gps.required', 0);

        if ($gpsRequired) {
            $rules['gps_location'][] = ['required'];
        }

        return $rules;
    }

    /**
     * Function returns current user device type
     *
     * @return string device type
     */
    protected function getCurrentUserDeviceType()
    {
        $device_type = null;

        /** @var User $user */
        $user = Auth::check() ? Auth::user() : null;
        if ($user) {
            $device_type = $user->getAccessToken()->device_type;
        }

        return $device_type;
    }
}
