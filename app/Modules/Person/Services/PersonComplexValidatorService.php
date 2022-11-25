<?php

namespace App\Modules\Person\Services;

use Illuminate\Support\Facades\App;
use App\Core\InputFormatter;
use App\Modules\Address\Http\Requests\AddressRequest;
use App\Modules\Contact\Http\Requests\ContactRequest;
use App\Modules\Person\Exceptions\PersonComplexException;
use App\Modules\Person\Http\Requests\PersonRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Class that will handle validation for addresses and contacts
 *
 * This class validates separately each address and contact that should be
 * created and create formatted array of errors (together with Person errors).
 *
 * It also formats input data according to Request rules that will be used later
 * for storing objects (if there are no validation errors).
 *
 *
 * @author    Marcin Nabiałek <http://marcin.nabialek.org>
 * @package   App\Modules\Person\Services
 * @version   1.0 (created 2015-01-30 08:41)
 */
class PersonComplexValidatorService
{
    // @todo - in future pieces of code may be reused from BulkValidatorService

    /**
     * PersonRequest object
     *
     * @var PersonRequest
     */
    protected $request;

    /**
     * ContactRequest object
     *
     * @var ContactRequest
     */
    protected $contactRequest;

    /**
     * AddressRequest object
     *
     * @var AddressRequest
     */
    protected $addressRequest;

    /**
     * InputFormatter object
     *
     * @var InputFormatter
     */
    protected $inputFormatter;

    /**
     * Basic Address validation rules
     *
     * @var array
     */
    protected $addressRules;

    /**
     * Basic Contact validation rules
     *
     * @var array
     */
    protected $contactRules;

    /**
     * Number of total validation errors
     *
     * @var int
     */
    protected $errorsCount = 0;

    /**
     * All errors
     *
     * @var array
     */
    protected $errors;

    /**
     * Available Contact types
     *
     * @var array
     */
    protected $contactTypes;

    /**
     * Whether validation was already launched
     *
     * @var bool
     */
    protected $validated = false;

    /**
     * Contact input formatter rules
     *
     * @var array
     */
    protected $contactFormatterRules;

    /**
     * Address input formatter rules
     *
     * @var
     */
    protected $addressFormatterRules;

    /**
     * Input data after formatting
     *
     * @var array
     */
    protected $data;


    /**
     * Set formatter rules, basic validation rules, and valid Contact types
     *
     * @param PersonRequest $personRequest
     * @param AddressRequest $addressRequest
     * @param ContactRequest $contactRequest
     * @param InputFormatter $inputFormatter
     */
    public function __construct(
        PersonRequest $personRequest,
        AddressRequest $addressRequest,
        ContactRequest $contactRequest,
        InputFormatter $inputFormatter
    ) {
        $this->request = $personRequest;
        $this->contactRequest = $contactRequest;
        $this->addressRequest = $addressRequest;
        $this->inputFormatter = $inputFormatter;

        $this->addressFormatterRules
            = $this->addressRequest->getFormatterRules();
        $this->contactFormatterRules
            = $this->contactRequest->getFormatterRules();

        $contactModel = App::make(\App\Modules\Contact\Models\Contact::class);
        $this->contactTypes = $contactModel::$types;

        $this->setBaseRules();
    }

    /**
     * Checks if there were any validation errors. If validation was not
     * launched, it launched validation before checking status
     *
     * @return bool
     */
    public function fails()
    {
        if (!$this->validated) {
            $this->validate();
        }

        return $this->hasErrors();
    }

    /**
     * Verify if there are any errors
     *
     * @return bool
     */
    protected function hasErrors()
    {
        return (bool)($this->errorsCount);
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get data after formatting
     *
     * @return array
     */
    public function getFormattedData()
    {
        return $this->data;
    }

    /**
     * Validates all data from $request and set formatted data in case if there
     * are no errors and data will be used to store objects
     */
    public function validate()
    {

        // first person - it's already validated - we just get errors
        $errors = $this->request->getValidationErrors();
        if ($errors) {
            $this->errorsCount += count($errors);
        }
        $this->errors = $errors;

        $this->data = $this->request->all();

        // loop over person addresses
        $addresses = $this->request->input('addresses', []);

        foreach ($addresses as $aKey => $address) {
            $aRules = $this->addressRules;
            $aRules = $this->appendAddressRules($aRules, $address);

            $address = $this->formatAddress($address);
            $this->data['addresses'][$aKey] = $address;

            $validator = \Validator::make($address, $aRules);

            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();
                $this->errorsCount += count($errors);
                $this->errors['addresses'][$aKey] = $errors;
            } else {
                $this->errors['addresses'][$aKey] = [];
            }

            // loop over contacts for current address

            if (!isset($addresses[$aKey]['contacts'])) {
                continue;
            }

            foreach ($addresses[$aKey]['contacts'] as $cKey => $contact) {
                $cRules = $this->contactRules;
                $cRules = $this->appendContactRules($cRules, $contact);

                $contact = $this->formatContact($contact);
                $this->data['addresses'][$aKey]['contacts'][$cKey] = $contact;

                $validator = \Validator::make($contact, $cRules);

                if ($validator->fails()) {
                    $errors = $validator->errors()->toArray();
                    $this->errorsCount += count($errors);
                    $this->errors['addresses'][$aKey]['contacts'][$cKey]
                        = $errors;
                } else {
                    $this->errors['addresses'][$aKey]['contacts'][$cKey] = [];
                }
            } // end foreach for contacts
        } // end foreach for addresses

        // now loop over contacts not assigned to any address

        $contacts = $this->request->input('contacts', []);

        if ($contacts) {
            foreach ($contacts as $cKey => $contact) {
                $cRules = $this->contactRules;
                $cRules = $this->appendContactRules($cRules, $contact);

                $contact = $this->formatContact($contact);
                $this->data['contacts'][$cKey] = $contact;

                $validator = \Validator::make($contact, $cRules);

                if ($validator->fails()) {
                    $errors = $validator->errors()->toArray();
                    $this->errorsCount += count($errors);
                    $this->errors['contacts'][$cKey] = $errors;
                } else {
                    $this->errors['contacts'][$cKey] = [];
                }
            }
        }
        $this->validated = true;

        if ($this->hasErrors()) {
            $this->invalidDataAction();
        }
    }

    /**
     * Throws PersonComplexException in case data is invalid
     *
     * @throws PersonComplexException
     */
    protected function invalidDataAction()
    {
        $validationException = App::make(PersonComplexException::class);
        $validationException->setFields($this->errors());

        throw $validationException;
    }

    /**
     * Format address data
     *
     * @param $address
     *
     * @return array
     */
    protected function formatAddress(array $address)
    {
        return $this->inputFormatter->format(
            new ParameterBag($address),
            $this->addressFormatterRules
        )->all();
    }

    /**
     * Format contact data
     *
     * @param $contact
     *
     * @return array
     */
    protected function formatContact(array $contact)
    {
        return $this->inputFormatter->format(
            new ParameterBag($contact),
            $this->contactFormatterRules
        )->all();
    }

    /**
     * Set base validation rules for address and contacts
     */
    protected function setBaseRules()
    {
        $this->setAddressBaseRules();
        $this->setContactBaseRules();
    }

    /**
     * Set base validation rules for address
     */
    protected function setAddressBaseRules()
    {
        $this->addressRules = $this->addressRequest->getRules();
        unset($this->addressRules['person_id']);
    }

    /**
     * Set base validation rules for contact
     */
    protected function setContactBaseRules()
    {
        $this->contactRules = $this->contactRequest->getRules();
        unset($this->contactRules['person_id']);
        unset($this->contactRules['address_id']);

        /* Unset all type_id rules. One of them is based on $typeKey. It
           will be added later (@see appendContactRules)
        */
        unset($this->contactRules['type_id']);
        // set required type_id rule
        $this->contactRules['type_id'][] = 'required';
    }

    /**
     * Appends validation rules for address based on current address data
     *
     * @param array $rules
     * @param array $data
     *
     * @return array
     */
    protected function appendAddressRules(array $rules, array $data)
    {
        // if necessary caching could be made here
        if (isset($data['state']) && isset($data['country'])) {
            $country = DB::table('countries')->where('code', $data['country'])
                ->first();

            if ($country) {
                $rules['state'][]
                    = 'exists:states,code,country_id,' . $country->id;
            }
        }

        return $rules;
    }


    /**
     * Appends validation rules for contact based on current contact data
     *
     * @param array $rules
     * @param array $data
     *
     * @return array
     */
    protected function appendContactRules(array $rules, array $data)
    {
        // if necessary caching could be made here

        $typeKey = $this->contactRequest->getTypeKey(
            $data,
            $this->contactTypes
        );

        // adding rule that was earlier unset (@see setContactBaseRules)
        $rules['type_id'][]
            = 'exists:type,type_id,type,contact,type_key,' . $typeKey;

        $rules = $this->contactRequest->appendValueRules(
            $rules,
            $typeKey,
            $this->contactTypes
        );

        return $rules;
    }
}
