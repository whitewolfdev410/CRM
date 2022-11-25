<?php

namespace App\Core\Extended;

/**
 * Custom validator
 * It exists because we would like to have custom error messages if few cases
 * To do that, we have to override passes() function
 */
class Validator extends \Illuminate\Validation\Validator
{
    /**
     * Determine if the data passes the validation rules.
     *
     * @return bool
     */
    public function passes()
    {
        // MessageBag from our namespace
        $this->messages = new MessageBag();

        // We'll spin through each rule, validating the attributes attached to that
        // rule. Any error messages will be added to the containers with each of
        // the other error messages, returning true if we don't have messages.
        foreach ($this->rules as $attribute => $rules) {
            $attribute = str_replace('\.', '->', $attribute);

            foreach ($rules as $rule) {
                $this->validateAttribute($attribute, $rule);

                if ($this->shouldStopValidating($attribute)) {
                    break;
                }
            }
        }

        // Here we will spin through all of the "after" hooks on this validator and
        // fire them off. This gives the callbacks a chance to perform all kinds
        // of other validation that needs to get wrapped up in this operation.
        foreach ($this->after as $after) {
            call_user_func($after);
        }

        return $this->messages->isEmpty();
    }

    /**
     * The same as in parent class but we want to use custom MessageBag
     * (from current namespace and not from Illuminate namespace)
     *
     * @return bool
     */
    public function passesold()
    {
        $this->messages = new MessageBag();

        // We'll spin through each rule, validating the attributes attached to that
        // rule. Any error messages will be added to the containers with each of
        // the other error messages, returning true if we don't have messages.
        foreach ($this->rules as $attribute => $rules) {
            foreach ($rules as $rule) {
                $this->validate($attribute, $rule);
            }
        }

        // Here we will spin through all of the "after" hooks on this validator and
        // fire them off. This gives the callbacks a chance to perform all kinds
        // of other validation that needs to get wrapped up in this operation.
        foreach ($this->after as $after) {
            call_user_func($after);
        }

        return count($this->messages->all()) === 0;
    }

    /**
     * Determine if the attribute is validatable.
     *
     * @param  object|string  $rule
     * @param  string  $attribute
     * @param  mixed   $value
     * @return bool
     */
    protected function isValidatable($rule, $attribute, $value)
    {
        return parent::isValidatable($rule, $attribute, $value) &&
            ($this->isImplicit($rule) || !empty($value)); // assume 'nullable' rule on all attributes by default
    }
}
