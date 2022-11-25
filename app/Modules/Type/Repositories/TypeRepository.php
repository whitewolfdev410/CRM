<?php

namespace App\Modules\Type\Repositories;

use App\Core\AbstractRepository;
use App\Modules\File\Repositories\FileRepository;
use App\Modules\Type\Http\Requests\TypeRequest;
use App\Modules\Type\Models\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Type repository class
 */
class TypeRepository extends AbstractRepository
{
    protected $searchable
        = [
            'type_id',
            'type',
            'type_key',
            'type_value',
            'sub_type_id',
            'color',
            'orderby',
            'created_at',
            'updated_at',
        ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Type      $type
     */
    public function __construct(Container $app, Type $type)
    {
        parent::__construct($app, $type);
    }

    /**
     * Creates and stores new Type object
     *
     * @param array $input
     *
     * @return Type
     */
    public function create(array $input)
    {
        /** @var Type $model */
        $model = null;

        if (!isset($input['orderby']) || $input['orderby'] === null) {
            $result = DB::select('
              SELECT max(orderby)+1 AS `orderby`
              FROM type WHERE type = ?', [$input['type']]);

            $input['orderby'] = $result[0]->orderby;

            if ($input['orderby'] === null) {
                $input['orderby'] = 0;
            }
        }

        $model = $this->model->create($input);

        return $model;
    }

    /**
     * Returns paginated collection of Type with default order optionally with
     * subtypes. In case `type` condition is used it will also apply to subtypes
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = ['type', 'orderby', 'type_value']
    ) {
        /** @var Type|Object $model */
        $model = $this->model;

        // verify if user wants also subtypes
        $withSubtypes = $this->request->query('with_subtypes', 0);
        $withIcons = $this->request->query('with_icons', 1);

        $input = $this->getInput();

        if (Arr::has($input, 'type') && config('app.crm_user') == 'bfc'
            && substr($input['type'], 0, 5) == 'asset'
            && Arr::has($this->BFC_ASSET_TYPES, $neededType = substr($input['type'], 6))
        ) {
            $resultArray = [];
            $i = 0;
            foreach ($this->BFC_ASSET_TYPES[$neededType] as $name => $value) {
                $resultArray['data'][] = [
                    'id'         => '',
                    'type_key'   => $input['type'] . '.' . strtolower($value),
                    'type_value' => $value,
                    "color"      => null,
                    "orderby"    => $i,
                ];
                $i++;
            }

            return Collection::make($resultArray);
        }
        
        if ($withSubtypes) {
            // get columns selected by user (or default)
            $columns = $this->getColumnsList();

            // get conditions used by user
            $conditions = $this->getConditionsArray();

            /* verify if user used type condition - if yes, this condition
               should be also applied to any subtypes
            */
            $typeCondition = [];
            foreach ($conditions as $condition) {
                if ($condition['column'] == 'type') {
                    $typeCondition = [$condition];
                }
            }

            // add subtypes
            $model = $model->with([
                'subTypes' => function ($q) use ($columns, $typeCondition) {
                    // add sub_type_id to bind with parent
                    if (!in_array('sub_type_id', $columns)) {
                        $columns[] = 'sub_type_id';
                    }

                    // apply type condition if exists
                    if ($typeCondition) {
                        $q = $this->applyConditions($q, $typeCondition);
                    }

                    // select same columns as for parent and order
                    $q->select($columns)->orderBy('orderby');
                },
            ]);
        }

        // use above queries for paginate
        $this->setWorkingModel($model);

        $data = parent::paginate($perPage, $columns, $order);

        // clear used model to prevent any unexpected actions
        $this->clearWorkingModel();

        if ($withIcons && $data->count()) {
            $this->appendIcons($data, $withSubtypes);
        }
        
        return $data;
    }

    /**
     * Get all types with specified type
     *
     * @param string       $type
     * @param string|array $columns
     *
     * @return Collection
     *
     * @throws InvalidArgumentException
     */
    public function getAll($type, $columns = '*')
    {
        /** @var Builder|Type $model */
        $model = $this->model;

        $query = $model
            ->where('type', $type)
            ->select($columns)
            ->orderBy('orderby');

        return $query->get();
    }

    /**
     * Return Model object by given $id
     *
     * @param int   $id
     * @param array $columns
     *
     * @return Type|null
     *
     * @throws ModelNotFoundException
     */
    public function find($id, array $columns = ['*'])
    {
        $columns = [
            'type.*',
            't2.type_value AS sub_type_id_value',
        ];

        /** @var Builder|Object|Type $model */
        $model = $this->model;

        $model = $model
            ->selectRaw(implode(', ', $columns))
            ->leftJoin('type AS t2', 'type.sub_type_id', '=', 't2.type_id');

        $this->setWorkingModel($model);

        /** @var Type $object */
        $object = parent::find($id);

        $this->clearWorkingModel();

        return $object;
    }

    /**
     * Return Model by given key (first record only)
     *
     * @param string $key
     * @param bool   $withChildren
     *
     * @return Type|null
     *
     * @throws InvalidArgumentException
     */
    public function findByKey($key, $withChildren = false)
    {
        /** @var Builder|Object|Type $model */
        $model = $this->model;

        $model = $model->where('type_key', $key);

        if ($withChildren) {
            $model = $model->with('subTypes');
        }

        return $model->first();
    }

    /**
     * Get $column for given type $key. If no record is found return null. If
     * $withChildren is set to true, array of $column will be returned
     *
     * @param string $key
     * @param bool   $withChildren
     * @param string $column
     *
     * @return array|mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function getColumnByKey(
        $key,
        $withChildren = false,
        $column = 'type_id'
    ) {
        $return = null;

        $record = $this->findByKey($key, $withChildren);

        if ($record) {
            if ($withChildren) {
                $ids[] = $record->{$column};
                if ($record->subTypes) {
                    $ids = array_merge(
                        $ids,
                        $record->subTypes->pluck($column)->all()
                    );
                }
                $return = $ids;
            } else {
                $return = $record->{$column};
            }
        }

        return $return;
    }

    /**
     * Get type and subtypes for given type $key.
     *
     * @param string $key
     *
     * @return array|mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function getAllByKey(
        $key
    ) {
        $types = [];

        $record = $this->findByKey($key, true);

        if ($record) {
            $types[$record->getId()] = $record->getTypeValue();

            if ($record->subTypes->pluck('type_value', 'type_id')->all()) {
                foreach ($record->subTypes as $subType) {
                    /** @var Type $subType */
                    $types[$subType->getId()] = $subType->getTypeValue();
                }
            }
        }

        return $types;
    }


    /**
     * Get types for given type $type.
     *
     * @param array $types
     *
     * @return array
     */
    public function getAllKeyByTypes($types)
    {
        return $this->model->whereIn('type', $types)
            ->pluck('type_key', 'type_id')
            ->all();
    }

    /**
     * Get id (or array of ids if $withChildren set to true) for given type $key
     *
     * @param string $key
     * @param bool   $withChildren
     *
     * @return array|mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function getIdByKey($key, $withChildren = false)
    {
        return $this->getColumnByKey($key, $withChildren, 'type_id');
    }

    /**
     * Get value (or array of values if $withChildren set to true) for given
     * type $key
     *
     * @param string $key
     * @param bool   $withChildren
     *
     * @return array|mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function getValueByKey($key, $withChildren = false)
    {
        return $this->getColumnByKey($key, $withChildren, 'type_value');
    }

    /**
     * Get value for given $id
     *
     * @param int $id
     *
     * @return array|mixed|null
     */
    public function getValueById($id)
    {
        $record = parent::findSoft((int)$id);
        if ($record) {
            return $record->type_value;
        }

        return null;
    }

    /**
     * Get key for given $id
     *
     * @param int $id
     *
     * @return array|mixed|null
     */
    public function getKeyById($id)
    {
        $record = parent::findSoft((int)$id);
        if ($record) {
            return $record->type_key;
        }

        return null;
    }

    /**
     * Get list by keys
     *
     * @param array  $keys
     * @param string $value
     * @param string $key
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getListByKeys(
        array $keys = [],
        $value = 'type_id',
        $key = 'type_key'
    ) {
        /** @var Builder|Object|Type $model */
        $model = $this->model;

        if ($keys) {
            $model = $model->whereIn('type_key', $keys);
        } else {
            $model = $model->where('type_key', '<>', '');
        }
        $this->setWorkingModel($model);

        try {
            $list = $this->grabList($value, $key);
        } catch (\PDOException $e) {
            $list = [];
        }
        
        $this->clearWorkingModel();

        return $list;
    }

    /**
     * Return list of items
     *
     * @param mixed  $types
     * @param string $value
     * @param string $key
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function getList($types, $value = 'type_value', $key = 'type_id')
    {
        list($model, ) = $this->getListModel($types);

        $this->setWorkingModel($model);

        $list = $this->grabList($value, $key);
        $this->clearWorkingModel();

        return $list;
    }

    /**
     * Get list of items by ids
     *
     * @param array $ids
     *
     * @return array
     */
    public function getListByIds(array $ids)
    {
        /** @var Builder|Object|Type $model */
        $model = $this->model;

        $model = $model
            ->whereIn('type_id', $ids)
            ->orderBy('type')
            ->orderBy('orderby')
            ->orderBy('type_value');

        $this->setWorkingModel($model);

        $data = $this->grabList();
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Grab list from database
     *
     * @param string $value
     * @param string $key
     *
     * @return array
     */
    protected function grabList($value = 'type_value', $key = 'type_id')
    {
        return parent::pluck($value, $key);
    }

    /**
     * Get list model and columns that could be used for joining with other
     * tables
     *
     * @param string|array $types Type in 'type' table
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getListModel($types)
    {
        if (!is_array($types)) {
            $types = [$types];
        }
        $this->setRawColumns(true);

        /** @var Builder|Object|Type $model */
        $model = $this->model;

        $model = $model->where(function ($q) use ($types) {
            /** @var Builder $q */
//            $q = $q->whereRaw('1 = 1');

            foreach ($types as $type) {
                $operator = '=';
                if (str_contains_any($type, '%')) {
                    $operator = 'LIKE';
                }
                $q->orWhere('type', $operator, $type);
            }
        });

        $model = $model
            ->orderBy('type')
            ->orderBy('orderby')
            ->orderBy('type_value');

        $columns = ['type.type_id', 'type.type_value'];

        return [$model, $columns];
    }

    /**
     * Get multiple types list divided by type
     *
     * @param array $types
     *
     * @return array
     */
    public function getMultipleLists(array $types)
    {
        $columns = ['type.type_id', 'type.type_value', 'type.type'];

        /** @var Builder|Object|Type $model */
        $model = $this->model;

        $types = $model
            ->whereIn('type', $types)
            ->select($columns)
            ->orderBy('orderby')
            ->orderBy('type_value')
            ->get();

        $data = [];

        foreach ($types as $type) {
            $data[$type->type][$type->id] = $type->type_value;
        }

        return $data;
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new TypeRequest();

        return $req->getFrontendRules();
    }

    /**
     * Function that will return due date based on provided terms type_id and date
     * @param $paymentTermsID
     * @param $date
     * @param string $type
     * @return false|string
     */
    public function getDueDateByTypeIdAndDate($paymentTermsID, $date, $type = 'invoice')
    {
        if ($paymentTermsID > 0) {
            $paymentTerm = $this->getValueById($paymentTermsID);
            return $this->getDueDateByTypeNameAndDate($paymentTerm, $date, $type);
        }
        return date('Y-m-d', strtotime($date));
    }

    /**
     * @param $paymentTerm
     * @param $date
     * @param string $type
     * @return false|string
     */
    public function getDueDateByTypeNameAndDate($paymentTerm, $date, $type = 'invoice')
    {
        $objectDate = $date;
        switch ($paymentTerm) {
            case 'Net 5':
            case 'Net in 5 Days':
                return date('Y-m-d', strtotime('+5 days', strtotime($objectDate)));
                break;
                
            case 'Net 10':
            case 'Net in 10 Days':
                return date('Y-m-d', strtotime('+10 days', strtotime($objectDate)));
                break;
                
            case 'Net in 75 Days':
                return date('Y-m-d', strtotime('+75 days', strtotime($objectDate)));
                break;

            case 'Net in 60 Days':
            case 'Net 60':
                return date('Y-m-d', strtotime('+60 days', strtotime($objectDate)));
                break;

            case 'Net in 45 Days':
            case 'Net 45':
                return date('Y-m-d', strtotime('+45 days', strtotime($objectDate)));
                break;

            case 'Net in 15 Days':
            case 'Net 15':
                return date('Y-m-d', strtotime('+15 days', strtotime($objectDate)));
                break;

            case 'Net in 30 Days':
            case 'Net 30':
            case '2% 10 Net 30':
                return date('Y-m-d', strtotime('+30 days', strtotime($objectDate)));
                break;

            case 'Net in 90 Days':
            case 'Net 90':
                return date('Y-m-d', strtotime('+90 days', strtotime($objectDate)));
                break;

            case '25th of next month':
                return date('Y-m-25', strtotime($objectDate . '+1 month'));
                break;
            case 'Payable in Advance':
            case 'Due on Receipt':
            default:
                if ($type == 'invoice') {
                    $crmUser = config('app.crm_user', 'clm');
                    if ($crmUser == 'aal') {
                        return date('Y-m-d', strtotime($objectDate));
                    } else {
                        #FIXME: why are we adding +30 days when 'due on receipt' ?? should this date not be the same as the invoice->date ??
                        return date('Y-m-d', strtotime('+30 days', strtotime($objectDate)));
                    }
                } else {
                    return $objectDate;
                }
                break;
        }
    }

    public function destroy($id)
    {
        $status = $this->model->destroy($id);

        if ($status) {
            return [
                'code' => 200,
                'data' => [
                    'message' => 'Type has been deleted successfully.'
                ]
            ];
        } else {
            return [
                'code' => 422,
                'data' => [
                    'error' => [
                        'message' => 'Could not delete the type.'
                    ]
                ]
            ];
        }
    }

    private $BFC_ASSET_TYPES = [
        'heat_type'        => [
            'Building Supplied' => 'Building Supplied',
            'Electric'          => 'Electric',
            'Gas'               => 'Gas',
            'Heat Pump'         => 'Heat Pump',
            'Hot Water'         => 'Hot Water',
            'Oil'               => 'Oil',
            'Steam'             => 'Steam',
            'WSHP'              => 'WSHP'
        ],
        'system_type'      => [
            'AHU'                           => 'AHU',
            'Air Curtain'                   => 'Air Curtain',
            'Auto Door'                     => 'Auto Door',
            'Baler'                         => 'Baler',
            'Boiler'                        => 'Boiler',
            'Chilled Water AHU'             => 'Chilled Water AHU',
            'Chiller'                       => 'Chiller',
            'CU'                            => 'CU',
            'Custom'                        => 'Custom',
            'EMS'                           => 'EMS',
            'Exhaust Fan / System'          => 'Exhaust Fan / System',
            'Generator'                     => 'Generator',
            'Gutter'                        => 'Gutter',
            'HVAC Condenser'                => 'HVAC Condenser',
            'HVAC'                          => 'HVAC',
            'Ice Machine'                   => 'Ice Machine',
            'LL Supplied / Mall Maintained' => 'LL Supplied / Mall Maintained',
            'Makeup Air Unit'               => 'Makeup Air Unit',
            'Open Air Cases (CID)'          => 'Open Air Cases (CID)',
            'RA Grill'                      => 'RA Grill',
            'Reach-in Case (Refrigeration)' => 'Reach-in Case (Refrigeration)',
            'Reach-In Cooler'               => 'Reach-In Cooler',
            'Reach-In Freezer'              => 'Reach-In Freezer',
            'Refrigeration Racks'           => 'Refrigeration Racks',
            'Remote CU (Refrigeration)'     => 'Remote CU (Refrigeration)',
            'RTU'                           => 'RTU',
            'Self Contained'                => 'Self Contained',
            'Split System'                  => 'Split System',
            'Unit Heater'                   => 'Unit Heater',
            'Vault Condenser'               => 'Vault Condenser',
            'Vault Evaporator'              => 'Vault Evaporator',
            'VAV Box'                       => 'VAV Box',
            'Walk-In Cooler'                => 'Walk-In Cooler',
            'Walk-In Freezer'               => 'Walk-In Freezer',
            'Walkin Box (Refrigeration)'    => 'Walkin Box (Refrigeration)',
            'Water Heater'                  => 'Water Heater',
            'WSHP'                          => 'WSHP',
        ],
        'voltage_type'     => [
            '120V/1PH'    => '120V/1PH',
            '208/230/1PH' => '208/230/1PH',
            '208/230/3PH' => '208/230/3PH',
            '460/3PH'     => '460/3PH'
        ],
        'fresh_air'        => [
            'Economizer'        => 'Economizer',
            'Manual ODA Damper' => 'Manual ODA Damper',
            'None'              => 'None'
        ],
        'refrigerant_type' => [
            'R-22'   => 'R-22',
            'R-410A' => 'R-410A',
            'R-407C' => 'R-407C',
            'R-134A' => 'R-134A',
            'R-404A' => 'R-404A',
            'Other'  => 'Other'
        ],
        'unit_condition'   => [
            'New'                     => 'New',
            'Very Good'               => 'Very Good',
            'Good'                    => 'Good',
            'Fair'                    => 'Fair',
            'Poor'                    => 'Poor',
            'Replacement Recommended' => 'Replacement Recommended',
            'Down'                    => 'Down',
            'Abandoned'               => 'Abandoned'
        ]
    ];

    /**
     * @param mixed  $data
     */
    private function appendIcons($data, $withSubtypes)
    {
        $typeIds = [];
        foreach ($data->items() as $item) {
            $typeIds[] = $item->getId();

            if ($withSubtypes && !empty($item->subTypes)) {
                foreach ($item->subTypes as $subType) {
                    $typeIds[] = $subType->getId();
                }
            }
        }

        /** @var FileRepository $fileRepository */
        $fileRepository = app(FileRepository::class);

        $mappedFiles = [];
        $files = $fileRepository->getFilesByTableNameAndTableIds('type', $typeIds);
        foreach ($files as $file) {
            $mappedFiles[$file->table_id] = $file;
        }

        foreach ($data->items() as $item) {
            $item->icon_url = isset($mappedFiles[$item->getId()])
                ? $mappedFiles[$item->getId()]->getLink()
                : null;

            if ($withSubtypes && !empty($item->subTypes)) {
                foreach ($item->subTypes as $subType) {
                    $subType->icon_url = isset($mappedFiles[$subType->getId()])
                        ? $mappedFiles[$subType->getId()]->getLink()
                        : null;
                }
            }
        }
    }
}
