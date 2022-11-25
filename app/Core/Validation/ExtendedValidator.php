<?php

namespace App\Core\Validation;

use App\Core\Extended\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class ExtendedValidator extends Validator
{
    /**
     * Check if the current current date is after or equal the given date
     *
     * Use it like so
     * 'after_or_equal' => 'date'
     *
     * @param $attribute
     * @param $value
     * @param $parameters
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function validateAfterOrEqual($attribute, $value, $parameters)
    {
        if (count($parameters) != 1) {
            throw new \InvalidArgumentException('1 parameter is required for after_or_equal rule');
        }

        return strtotime($parameters[0]) <= strtotime($value);
    }

    protected function replaceAfterOrEqual($message, $attribute, $rule, $parameters)
    {
        return str_replace([':date'], $parameters[0], $message);
    }

    /**
     * Check if the state is used in any addresses and if it is it doesn't allow
     * to change state code or country
     *
     * @param string $attribute
     * @param string $value
     * @param array  $parameters
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function validateStateNotUsed($attribute, $value, $parameters)
    {
        $allowedAttributes = ['code', 'country_id'];

        if (!in_array($attribute, $allowedAttributes)) {
            throw new \InvalidArgumentException('Invalid attribute for state_not_used rule');
        }

        if (count($parameters) != 1) {
            throw new \InvalidArgumentException('1 parameter is required for state_not_used rule');
        }

        $record = DB::table('states')
            ->join('countries', 'states.country_id', '=', 'countries.id')
            ->select(
                'states.code AS state_code',
                'countries.code AS country_code',
                'country_id'
            )
            ->where('states.id', $parameters[0])->first();

        if (!$record) {
            return false;
        }

        if ($attribute == 'code' && $value == $record->state_code) {
            return true;
        }

        if ($attribute == 'country_id' && $value == $record->country_id) {
            return true;
        }

        $exist = DB::table('address')->where('state', $record->state_code)
            ->where('country', $record->country_code)->first();

        if ($exist) {
            return false;
        }

        return true;
    }

    /**
     * Check if the country is used in any addresses and if it is it doesn't
     * allow to change country code
     *
     * @param string $attribute
     * @param string $value
     * @param array  $parameters
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function validateCountryNotUsed($attribute, $value, $parameters)
    {
        $allowedAttributes = ['code'];

        if (!in_array($attribute, $allowedAttributes)) {
            throw new \InvalidArgumentException('Invalid attribute for country_not_used rule');
        }

        if (count($parameters) != 1) {
            throw new \InvalidArgumentException('1 parameter is required for country_not_used rule');
        }

        $record = DB::table('countries')->where('id', $parameters[0])->first();

        if (!$record) {
            return false;
        }

        if ($attribute == 'code' && $value == $record->code) {
            return true;
        }

        $exist = DB::table('address')->where('country', $record->code)->first();

        if ($exist) {
            return false;
        }

        return true;
    }

    /**
     * Check if the currency is used in any countries and if it is it doesn't
     * allow to change currency code
     *
     * @param string $attribute
     * @param string $value
     * @param array  $parameters
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function validateCurrencyNotUsed($attribute, $value, $parameters)
    {
        $allowedAttributes = ['code'];

        if (!in_array($attribute, $allowedAttributes)) {
            throw new \InvalidArgumentException('Invalid attribute for country_not_used rule');
        }

        if (count($parameters) != 1) {
            throw new \InvalidArgumentException('1 parameter is required for country_not_used rule');
        }

        $record = DB::table('currencies')->where('id', $parameters[0])->first();

        if (!$record) {
            return false;
        }

        if ($attribute == 'code' && $value == $record->code) {
            return true;
        }

        $exist = DB::table('countries')->where('currency', $record->code)
            ->first();

        if ($exist) {
            return false;
        }

        return true;
    }
}
