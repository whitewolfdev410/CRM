<?php

namespace App\Core\Exceptions;

abstract class NestedValidationException extends ValidationException
{
    /**
     * Fields that are nested and should be handled in nested
     *
     * @var array
     */
    protected $nestedFields = [];

    /**
     * {@inheritdoc}
     */
    public function setFields(array $errors, $returnOnly = false)
    {
        $fields = [];

        $messages = [$this->trans->get('validation_nested_error')];
        $data = [];

        foreach ($errors as $fieldName => $fieldMessages) {
            $nested = [];

            if (!in_array($fieldName, $this->nestedFields)) {
                // we pass only one field errors so we grab only first element errors
                $field = $this->getFieldsErrors([$fieldName => $fieldMessages])[0];
            } else {
                foreach ($errors[$fieldName] as $fieldNestedMessages) {
                    $nested[] =
                        [
                            'fields' => $this->getFieldsErrors($fieldNestedMessages),
                        ];
                }

                $field = (object)[
                    'name' => $fieldName,
                    'messages' => $messages,
                    'data' => $data,
                    'nested' => $nested,
                ];
            }

            $fields[] = $field;
        }

        $this->fields = $fields;

        return $this;
    }

    /**
     * Get simple errors for one object
     *
     * @param array $errors
     * @return array
     */
    protected function getFieldsErrors(array $errors)
    {
        return parent::setFields($errors, true);
    }
}
