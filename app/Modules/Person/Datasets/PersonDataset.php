<?php

namespace App\Modules\Person\Datasets;

use Illuminate\Support\Str;
use App\Core\Trans;
use App\Modules\Person\Exceptions\FunctionNotImplementedException;
use App\Modules\Type\Models\Type;
use App\Modules\Type\Repositories\TypeRepository;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;

class PersonDataset
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var mixed
     */
    protected $fields;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Sets all required data
     *
     * @param Container $app
     * @param string    $module
     */
    public function __construct(Container $app, $module)
    {
        $this->app = $app;
        $this->request = $app->make(Request::class);
        $this->config = $app->make(Repository::class);

        $this->fields = $this->config->get('modconfig.' . $module . '.fields');

        $this->typeRepository = new TypeRepository($this->app, new Type());

        $this->module = $module;

        $this->trans = $this->app->make(Trans::class);
    }

    /**
     * Sets column list based on $action
     *
     * @param $action
     */
    protected function setColumns($action)
    {
        $this->columns = $this->config->get('modconfig.' . $this->module . '.columns.' . $action);
    }

    /**
     * Sets all data - columns, rules and data
     *
     * @param $action
     *
     * @return array
     *
     * @throws FunctionNotImplementedException
     */
    public function run($action)
    {
        $this->setColumns($action);

        $output = [];

        $requestClassName = '\\App\\Modules\\Person\\Http\\Requests\\'
            . ucfirst(Str::camel($this->module . '_' . $action . '_request'));

        /** @var \App\Http\Requests\Request $req */
        $req = new $requestClassName();
        $rules = $req->getFrontendRules();

        foreach ($this->columns as $column) {
            $output[$column]['type'] = isset($this->fields[$column]['type'])
                ? $this->fields[$column]['type']
                : 'input';

            $output[$column]['label'] = $this->getLabel($column, $action);

            if (isset($this->fields[$column]['url'])) {
                $output[$column]['url'] = $this->fields[$column]['url'];
                $output[$column]['key'] = $this->fields[$column]['key'];
                $output[$column]['value'] = $this->fields[$column]['value'];
            } elseif (isset($this->fields[$column]['data'])) {
                if ($this->fields[$column]['data'] == 'func:') {
                    $functionName = 'get' . Str::studly($column . '_data');

                    if (method_exists($this, $functionName)) {
                        $cData = $this->$functionName();

                        $output[$column]['data'] = $cData;
                    } else {
                        $exception = $this->app->make(FunctionNotImplementedException::class);
                        $exception->setData([
                            'function_name' => $functionName,
                            'class'         => get_class($this),
                        ]);
                        throw $exception;
                    }
                } else {
                    $output[$column]['data'] = $this->fields[$column]['data'];
                }
            }
            if (isset($rules[$column])) {
                $output[$column] = array_merge($output[$column], $rules[$column]);
            }
        }

        return $output;
    }

    /**
     * Generated label
     *
     * @param string $column
     * @param string $action
     *
     * @return string
     */
    protected function getLabel($column, $action)
    {
        return $this->trans->getColumn($this->module . '.' . $action . '.'
            . $column);
    }

    /**
     * Prepares data for joins and list of columns to display
     *
     * @param string $action
     *
     * @return mixed
     */
    protected function runJoinsAndColumns($action)
    {
        $this->setColumns($action);

        $output['joins'] = [];
        $output['columns'] = [];

        foreach ($this->columns as $column) {
            if (isset($this->fields[$column]['url'])
                || (isset($this->fields[$column]['data'])
                    && $this->fields[$column]['data'] == 'func:')
            ) {
                $functionName = 'get' . Str::studly($column . '_detailed_data');
                if (method_exists($this, $functionName)) {
                    $funcData = $this->$functionName();
                    if ($funcData) {
                        if ($funcData['join']) {
                            $output['joins'][] = $funcData['join'];
                        }
                        $output['columns'] = array_merge(
                            $output['columns'],
                            $funcData['columns']
                        );
                    }
                } /*else {
                    do nothing - we might not want to get all detailed data
                    so function doesn't have to be implemented
                }*/
            }
        }

        return $output;
    }

    /**
     * Get detailed data (for joining with other resources)
     *
     * @param string $action
     *
     * @return mixed
     */
    public function getDetailedData($action)
    {
        return $this->runJoinsAndColumns($action);
    }

    /**
     * Get labels for columns
     *
     * @param string $action
     *
     * @return mixed
     */
    public function getLabels($action)
    {
        $this->setColumns($action);

        foreach ($this->columns as $column) {
            $output[$column]['label'] = $this->getLabel($column, $action);
        }

        return $output;
    }

    /**
     * Gets data
     *
     * @param $action
     *
     * @return array
     *
     * @throws \App\Modules\Person\Exceptions\FunctionNotImplementedException
     */
    public function getData($action)
    {
        return $this->run($action);
    }

    /**
     * Get salutation data
     *
     * @return array
     */
    public function getSalutationData()
    {
        return [
            'Mr.'  => $this->trans->get('general.salutation.mr'),
            'Mrs.' => $this->trans->get('general.salutation.mrs'),
            'Ms.'  => $this->trans->get('general.salutation.ms'),
        ];
    }

    /**
     * Get sex data
     *
     * @return array
     */
    public function getSexData()
    {
        return [
            'm' => $this->trans->get('general.sex.m'),
            'f' => $this->trans->get('general.sex.f'),
        ];
    }

    /**
     * Get type_id data
     *
     * @return mixed
     */
    public function getTypeIdData()
    {
        return $this->typeRepository->getList('person');
    }

    public function getTypeIdDetailedData()
    {
        return $this->getDetailedDataForType('type_id');
    }

    /**
     * Get status_type_id data
     *
     * @return mixed
     */
    public function getStatusTypeIdData()
    {
        return $this->typeRepository->getList('company_status');
    }


    public function getStatusTypeIdDetailedData()
    {
        return $this->getDetailedDataForType('status_type_id');
    }

    /**
     * Get industry_type_id data
     *
     * @return mixed
     */
    public function getIndustryTypeIdData()
    {
        return $this->typeRepository->getList('industry');
    }

    public function getIndustryTypeIdDetailedData()
    {
        return $this->getDetailedDataForType('industry_type_id');
    }

    /**
     * Get industry_type_id data
     *
     * @return mixed
     */
    public function getRotTypeIdData()
    {
        return $this->typeRepository->getList('rot');
    }

    public function getRotTypeIdDetailedData()
    {
        return $this->getDetailedDataForType('rot_type_id');
    }

    protected function getDetailedDataForType($field)
    {
        return [
            'join'    => [
                "type AS {$field}_type",
                "person.{$field}",
                '=',
                "{$field}_type.type_id",
            ],
            'columns' => ["{$field}_type.type_value AS `{$field}_value`"],
        ];
    }

    public function getReferralPersonIdDetailedData()
    {
        return $this->getDetailedDataForPerson('referral_person_id');
    }

    public function getAssignedToPersonIdDetailedData()
    {
        return $this->getDetailedDataForPerson('assigned_to_person_id');
    }

    public function getOwnerPersonIdDetailedData()
    {
        return $this->getDetailedDataForPerson('owner_person_id');
    }

    /**
     * Data for pricing_structure_id field (list for select)
     *
     * @return mixed
     */
    public function getPricingStructureIdData()
    {
        $psRepo = $this->typeRepository->makeRepository('PricingStructure');

        return $psRepo->getList();
    }

    /**
     * Detailed data for pricing_structure_id field (value for record)
     *
     * @return array
     */
    protected function getPricingStructureIdDetailedData()
    {
        return [
            'join'    => [
                'pricing_structure',
                'person.pricing_structure_id',
                '=',
                'pricing_structure.pricing_structure_id',
            ],
            'columns' => ['pricing_structure.structure_name AS `pricing_structure_id_value`'],
        ];
    }


    protected function getDetailedDataForPerson($field)
    {
        // optional kind for all related persons
        return [
            'join'    => [
                /*"person AS {$field}_person",
                "person.{$field}",
                '=',
                "{$field}_person.person_id"*/
            ],
            'columns' => [
                "person_name(person.{$field}) AS `{$field}_value`",
                /*"{$field}_person.kind AS `{$field}_kind`"*/
            ],
        ];
    }
}
