<?php

namespace App\Services;

use App\Core\Extended\Validator;
use App\Core\InputFormatter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;
use App\Http\Requests\Request as DataRequest;

/**
 * Class BulkValidatorService
 *
 * This class may be used to validate multiple elements at once. You need to
 * create Request object with validation rules for one element, one extra bulk
 * validation request just to verify whether data are send in valid key and
 * whether it's an array and create this object and run fails method. You should
 * define action that will be called when there are any errors, at the moment
 * the best option is to throw custom exception here that will be later catch
 * by Handler
 *
 * Sample usage:
 *
 * $validator = new BulkValidatorService(...);
 *
 * $validator->validate();
 *
 * // now run action you want and get data using $validator->getFormattedData();
 *
 *
 * @package App\Services
 */
abstract class BulkValidatorService
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Request object
     *
     * @var DataRequest
     */
    protected $dataRequest;

    /**
     * InputFormatter object
     *
     * @var InputFormatter
     */
    protected $inputFormatter;

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
     * Whether validation was already launched
     *
     * @var bool
     */
    protected $validated = false;

    /**
     * Input data after formatting
     *
     * @var array
     */
    protected $data;

    /**
     * Basic data validation rules
     *
     * @var array
     */
    protected $baseRules = [];

    /**
     * Formatter rules
     *
     * @var array
     */
    protected $formatterRules = [];

    /**
     * Input name where bulk data are stored
     *
     * @var string
     */
    protected $dataKey;

    /**
     * Set formatter rules, basic validation rules, and valid Contact types
     *
     * @param Request $request Standard Request object
     * @param DataRequest $dataRequest Request object that stores validation
     *     rules
     * @param InputFormatter $inputFormatter
     * @param string $dataKey
     */
    public function __construct(
        Request $request,
        DataRequest $dataRequest,
        InputFormatter $inputFormatter,
        $dataKey
    ) {
        $this->request = $request;
        $this->dataRequest = $dataRequest;
        $this->inputFormatter = $inputFormatter;

        $this->formatterRules = $this->getFormatterRules();
        $this->setBaseRules();
        $this->dataKey = $dataKey;
    }

    /**
     * Function that will be run when there are any errors in validate method
     *
     * @return mixed
     */
    abstract protected function invalidDataAction();

    /**
     * Get formatter rules
     *
     * @return array
     */
    protected function getFormatterRules()
    {
        $rules = $this->dataRequest->getFormatterRules();

        return $rules;
    }

    /**
     * Set base validation rules for data
     */
    protected function setBaseRules()
    {
        $this->baseRules = $this->dataRequest->getRules();
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
     * are no errors and data will be used to store objects. If there are any
     * errors invalidDataAction function will be called
     *
     */
    public function validate()
    {
        $this->getInitialErrors();

        $this->data = $this->request->all();

        $records = $this->request->input($this->dataKey, []);
        if (!is_array($records)) {
            $records = [$records];
        }

        $validator = $this->getValidator();

        // loop over items

        foreach ($records as $tsKey => $record) {
            // if it's not array it's not valid - set it to empty array
            if (!is_array($record)) {
                $record = [];
            }

            $tsRules = $this->getValidationRules($record);

            $record = $this->formatData($record);
            $this->data[$this->dataKey][$tsKey] = $record;

            $validator->setData($record);
            $validator->setRules($tsRules);

            if ($validator->fails()) {
                $errors = $validator->errors()->toArray();
                $this->errorsCount += count($errors);
                $this->errors[$this->dataKey][$tsKey] = $errors;
            } else {
                $this->errors[$this->dataKey][$tsKey] = [];
            }
        }

        $this->validated = true;
        if ($this->hasErrors()) {
            $this->invalidDataAction();
        }
    }

    /**
     * Set initial errors (from base request)
     */
    protected function getInitialErrors()
    {
        // by default it will be empty
    }

    /**
     * Get validation rules
     *
     * @param $data
     *
     * @return array
     */
    protected function getValidationRules($data)
    {
        $rules = $this->dataRequest->rules($data);

        return $rules;
    }

    /**
     * Get validator instance and apply extra validator rules to validator
     *
     * @return Validator
     */
    protected function getValidator()
    {
        \Validator::resolver(function ($translator, $data, $rules, $messages) {
            return new Validator($translator, $data, $rules, $messages);
        });

        $validator = \Validator::make([], []);

        return $validator;
    }

    /**
     * Format data
     *
     * @param $data
     *
     * @return array
     */
    protected function formatData(array $data)
    {
        return $this->inputFormatter->format(
            new ParameterBag($data),
            $this->formatterRules
        )->all();
    }
}
