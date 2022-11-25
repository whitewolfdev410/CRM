<?php

namespace App\Core;

use Illuminate\Support\Str;
use App\Core\Exceptions\ValidationUnknownRuleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\ParameterBag;

class InputFormatter
{
    /**
     * Custom rules functions
     *
     * @var array
     */
    private $customRules;

    /**
     * Constructor
     *
     * @param array $customRules optional custom rules - array of 'rule_name'
     *     => function($value) { ... }
     */
    public function __construct(array $customRules = [])
    {
        $this->customRules = $customRules;
    }

    /**
     * Formats input data according to the rules
     *
     * @param  ParameterBag $data
     * @param  array $rules
     *
     * @return ParameterBag
     */
    public function format(ParameterBag $data, array $rules)
    {
        $formatted = clone $data;

        foreach ($rules as $field => $rule) {
            if ($data->has($field)) {
                $value = $data->get($field);
                $formatted->set($field, $this->formatField($value, $rule));
            }
        }

        return $formatted;
    }

    /**
     * Formats single field
     *
     * @param  string $value
     * @param  string $rule
     *
     * @return mixed
     */
    protected function formatField($value, $rule)
    {
        $ruleName = Str::studly($rule);

        if ($customRule = Arr::get($this->customRules, $ruleName)) {
            return $customRule($value);
        }

        $method = 'format' . $ruleName;

        if (!method_exists($this, $method)) {
            $exception = App::make(ValidationUnknownRuleException::class);
            $exception->setData(['rule' => $rule]);
            throw $exception;
        }

        return $this->$method($value);
    }

    // rules:

    protected function formatInt($value)
    {
        return (int)$value;
    }

    protected function formatIntOrNull($value)
    {
        $v = trim($value);

        return ctype_digit($v) ? (int)$v : null;
    }

    protected function formatTrim($value)
    {
        return trim($value);
    }

    protected function formatTrimOrNull($value)
    {
        $v = trim($value);

        return $v == '' ? null : $v;
    }

    protected function formatTrimOrHash($value)
    {
        $v = trim($value);

        return $v == '' ? '#' : $v;
    }


    public function formatTrimUpper($value)
    {
        return mb_strtoupper(trim($value));
    }

    public function formatFloat($value)
    {
        return (float)$value;
    }


    /**
     * Convert string or array of strings to float (commas changed into dots)
     *
     * @param string|array $value
     *
     * @return float|array
     */
    public function formatSafeFloat($value)
    {
        $isArray = true;
        if (!is_array($value)) {
            $isArray = false;
            $value = [$value];
        }

        foreach ($value as $k => $v) {
            $value[$k] = (float)str_replace(',', '.', trim($v));
        }

        if ($isArray) {
            return $value;
        }

        return $value[0];
    }
}
