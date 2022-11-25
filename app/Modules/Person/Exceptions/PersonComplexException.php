<?php

namespace App\Modules\Person\Exceptions;

use App\Core\Exceptions\NestedValidationException;

class PersonComplexException extends NestedValidationException
{
    /**
     * {@inheritdoc}
     */
    protected $nestedFields = ['addresses', 'contacts'];

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
                $field = $this->getFieldsErrors([$fieldName => $fieldMessages]);
            } else {
                foreach ($errors[$fieldName] as $fieldNestedMessages) {
                    // unset contact from addresses
                    $simpleNestedMessages = $fieldNestedMessages;
                    unset($simpleNestedMessages['contacts']);

                    // get errors only for addresses (without nested contacts)
                    $nestedErrorMessages =
                        [
                            'fields' => $this->getFieldsErrors($simpleNestedMessages),
                        ];

                    // now process nested contacts inside addresses
                    if (array_key_exists('contacts', $fieldNestedMessages)) {
                        $conErrors = [];

                        // get errors for each nested contact
                        foreach ($fieldNestedMessages['contacts'] as $contactErrors) {
                            $conErrors[] =
                                ['fields' => $this->getFieldsErrors($contactErrors)];
                        }

                        // wrap all contacts errors with correct structure
                        $nestedErrorMessages['fields'][] =
                            (object)[
                                'name' => 'contacts',
                                'messages' => $messages,
                                'data' => $data,
                                'nested' => $conErrors,
                            ];
                    }

                    // add all addresses errors (together with contacts) to nested errors
                    $nested[] = $nestedErrorMessages;
                }

                $field = (object)[
                    'name' => $fieldName,
                    'messages' => $messages,
                    'data' => $data,
                    'nested' => $nested,
                ];
            }

            if (is_array($field)) {
                $fields = array_merge($fields, $field);
            } else {
                $fields[] = $field;
            }
        }

        $this->fields = $fields;

        return $this;
    }
}
