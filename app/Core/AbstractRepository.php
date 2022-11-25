<?php

namespace App\Core;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MongoDB\Driver\Query;

/**
 * Repository abstract class. In concrete class you need to define constructor
 * that will set $model to selected Model instance
 */
abstract class AbstractRepository
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * Repository model
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * Request object
     *
     * @var \Illuminate\Http\Request;
     */
    protected $request;

    /**
     * Request object
     *
     * @var \Illuminate\Contracts\Config\Repository
     */
    protected $config;

    /**
     * Repository working model - might be different than $model
     *
     * @var Object
     */
    protected $workingModel;

    /**
     * Input that may be used instead of default one
     *
     * @var array
     */
    protected $input;

    /**
     * Fields that will be used from request for filtering and sorting
     *
     * @var array
     */
    protected $searchable = [];

    /**
     * Map of columns that should be changed when applying sorting. If joins
     * are used, one column can appear in 2 or more tables and for example
     * sorting by created_date might cause ambiguous SQL error - in this case
     * we want to define that created_date is for example table.created_date
     *
     * @var array
     */
    protected $sortableMap = [];

    /**
     * @var bool
     */
    protected $rawColumns = false;

    /**
     * @var ColumnNameResolver
     */
    protected $columnNameResolver;

    /**
     * Table to use for person_name(table.column) expression
     *
     * @var string
     */
    protected $personNameTable = 'person';

    /**
     * Column to use for person_name(table.column) expression
     *
     * @var string
     */
    protected $personNameTableKey = 'person_id';

    /**
     * List of loaded repositories - used if we want to use repositories in
     * repositories but we don't want to inject them in constructor (some
     * repositories are used only in some methods)
     *
     * @var array
     */
    protected $repositories;

    /**
     * Model that will be used to generate count query. If it's set to null
     * working model will be used to generate count query
     *
     * @var Builder
     */
    protected $countModel;

    protected $withGroupBy = false;

    protected $availableColumns = [];

    protected $joinableTables = [];

    private $illegalAliases = [
        'id',
        'interval',
        'created_at',
        'updated_at'
    ];

    public function __construct(Container $app, $model)
    {
        $this->app = $app;
        $this->model = $model;
        $this->request = $app->make(Request::class);
        $this->config = $app->make(Repository::class);
        $this->columnNameResolver = $this->makeColumnNameResolver($model);
    }

    /**
     * Make column name resolver instance
     *
     * @param  Object  $model
     *
     * @return ColumnNameResolver
     */
    protected function makeColumnNameResolver($model)
    {
        return new ColumnNameResolver($model);
    }

    /**
     * Make query input parser instance
     *
     * @param  array  $input
     *
     * @return QueryInputParser
     */
    protected function makeInputParser(array $input)
    {
        return new QueryInputParser(
            $this->app,
            $input,
            $this->columnNameResolver,
            $this->searchable,
            isset($this->sortable) ? $this->sortable : $this->searchable,
            $this->sortableMap,
            $this->rawColumns
        );
    }

    /**
     * Return collection of Model
     *
     * @param  array  $columns  - by default returns all fields
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function all(array $columns = ['*'])
    {
        return $this->getModel()->get($columns);
    }

    /**
     * Sets status to rawColumns
     *
     * @param  bool  $status
     */
    public function setRawColumns($status)
    {
        $this->rawColumns = $status;
    }

    /**
     * Creates new instance of Model
     *
     * @param  array  $attributes
     *
     * @return Model
     */
    public function newInstance(array $attributes = [])
    {
        return $this->model->newInstance($attributes);
    }

    /**
     * Choose input to use
     *
     * @return array
     */
    protected function getInput()
    {
        return ($this->input === null) ? $this->request->query() : $this->input;
    }

    /**
     * Set default sort
     *
     * @param  string  $column
     */
    protected function setDefaultSort($column = '-id')
    {
        $input = $this->getInput();

        if (!isset($input['sort'])) {
            $input['sort'] = $column;
            $this->setInput($input);
        }
    }

    /**
     * Pagination - based on query url use either automatic paginator or
     * manual paginator
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  array  $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        $perPage = (int)$perPage;
        $input = $this->getInput();

        if (empty($input) || (count($input) === 1 && isset($input['page']))) {
            return $this->paginateSimple($perPage, $columns, $order);
        }

//        $maxRecords = $this->config->get('database.max_records');
        $maxRecords = 10000;

        return $this->paginateComplex($perPage, $columns, $order, $input, $maxRecords);
    }

    /**
     * Creates and stores new Model object
     *
     * @param  array  $input
     *
     * @return Model
     */
    public function create(array $input)
    {
        $input['creator_person_id'] = $this->getCreatorPersonId();

        return $this->model->create($input);
    }

    /**
     * Creates and stores new Model object without checking mass assignment. It
     * should be used ONLY for non-user input
     *
     * @param  array  $input
     *
     * @return Model
     */
    public function forceCreate(array $input)
    {
        $input['creator_person_id'] = $this->getCreatorPersonId();

        return $this->model->forceCreate($input);
    }

    /**
     * Get creator person id
     *
     * @return int
     */
    protected function getCreatorPersonId()
    {
        return getCurrentPersonId();
    }

    /**
     * Return Model object by given $id
     *
     * @param  int  $id
     * @param  array  $columns
     *
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find($id, array $columns = ['*'])
    {
        return $this->findInternal($id, $columns);
    }

    /**
     * Return Model object by given $id
     *
     * @param  int  $id
     * @param  array  $columns
     *
     * @return Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findInternal($id, array $columns = ['*'])
    {
        $model = $this->getModel();

        if ($this->rawColumns) {
            $result = $model->selectRaw(implode(', ', $columns))
                ->where($this->model->getKeyName(), $id)->first();

            if ($result === null) {
                throw with(new ModelNotFoundException())
                    ->setModel(get_called_class());
            }

            return $result;
        }

        return $model->findOrFail($id, $columns);
    }

    /**
     * Find model (basic)
     *
     * @param  int  $id
     * @param  array  $columns
     *
     * @return Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    final public function basicFind($id, array $columns = ['*'])
    {
        return $this->findInternal($id, $columns);
    }

    /**
     * Finds without failing
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @param  bool  $raw
     *
     * @return Model
     */
    public function findSoft($id, array $columns = ['*'], $raw = false)
    {
        $model = $this->getModel();

        if ($this->rawColumns || $raw) {
            return $model->selectRaw(implode(', ', $columns))
                ->where($this->model->getKeyName(), $id)->first();
        }

        return $model->find($id, $columns);
    }

    /**
     * Updates Model object identified by given $id with $input data
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $object = $this->getModel()->find($id);

        if ($object === null) {
            throw with(new ModelNotFoundException())
                ->setModel(get_called_class());
        }

        return $this->performUpdate($object, $input, true);
    }

    /**
     * Perform object update
     *
     * @param  Object  $object
     * @param  array  $input
     * @param  bool  $returnUpdated
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function performUpdate($object, array $input, $returnUpdated)
    {
        $object->update($input);

        if ($returnUpdated) {
            return $this->find($object->id);
        }
    }

    /**
     * Removes given Model object
     *
     * @param  array|int  $id
     *
     * @return bool
     */
    public function destroy($id)
    {
        return $this->model->destroy($id);
    }

    /**
     * Returns paginated collection of Models using default Paginator
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  array  $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     */
    protected function paginateSimple(
        $perPage,
        array $columns = ['*'],
        array $order = []
    ) {
        $model = $this->getModel();
        $modelData = clone $model;

        $parser = $this->makeInputParser($this->getInput());
        $page = $parser->getCurrentPage();

        return $this->paginateModel($modelData, $columns, [], $order, $page, $perPage);
    }

    /**
     * Sets custom input
     *
     * @param  array  $input
     */
    public function setInput($input)
    {
        $this->input = $input;
    }

    /**
     *  Clears custom input
     */
    public function clearInput()
    {
        $this->input = null;
    }

    /**
     * Returns model that will be used
     *
     * @return \Illuminate\Database\Eloquent\Model|Object
     */
    protected function getModel()
    {
        return ($this->workingModel === null) ?
            $this->model : $this->workingModel;
    }

    /**
     * Sets working model - it might be not a model but already query builder
     *
     * @param  Object  $model
     */
    public function setWorkingModel($model)
    {
        $this->workingModel = $model;
    }

    /**
     * Clears working model
     *
     */
    public function clearWorkingModel()
    {
        $this->workingModel = null;
    }

    /**
     * Get count model
     *
     * @return Builder
     */
    protected function getCountModel()
    {
        return $this->countModel;
    }

    /**
     * Set count model
     *
     * @param  Model  $model
     */
    protected function setCountModel($model)
    {
        $this->countModel = $model;
    }

    /**
     * Clear count model
     *
     */
    protected function clearCountModel()
    {
        $this->countModel = null;
    }

    /**
     * Apply filters to models base on request filters
     *
     * @param  int  $perPage
     * @param  array  $defColumns
     * @param  array  $defOrder
     * @param  array  $input
     * @param  int  $maxRecords
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected function paginateComplex(
        $perPage,
        array $defColumns,
        array $defOrder,
        array $input,
        $maxRecords
    ) {
        $model = $this->getModel();
        $modelData = clone $model;

        $parser = $this->makeInputParser($input);

        $conditions = $parser->createWhereConditions(
            [],
            $this->personNameTable,
            $this->personNameTableKey
        );
        $orderList = $parser->createOrderByList($defOrder);
        $selectColumns = $this->getColumnsList($defColumns, $parser);

        $page = $parser->getCurrentPage();
        $limit = $parser->getLimit($maxRecords, $perPage);
        $specials = $parser->getSpecials();

        return $this->paginateModel(
            $modelData,
            $selectColumns,
            $conditions,
            $orderList,
            $page,
            $limit,
            $specials
        );
    }

    /**
     * Get condition array based on input
     *
     * @return array
     */
    protected function getConditionsArray()
    {
        $input = $this->getInput();

        $parser = $this->makeInputParser($input);
        $conditions = $parser->createWhereConditions(
            [],
            $this->personNameTable,
            $this->personNameTableKey
        );

        return $conditions;
    }

    /**
     * Get input order array (only user input without default order)
     *
     * @return array
     */
    protected function getInputOrderArray()
    {
        $input = $this->getInput();

        $parser = $this->makeInputParser($input);

        return $parser->createOrderByList();
    }

    /**
     * Get columns list based on default columns and input
     *
     * @param  array  $defColumns
     * @param  QueryInputParser  $parser
     * @param  string  $columnsName
     *
     * @return array|string
     */
    protected function getColumnsList(
        array $defColumns = ['*'],
        QueryInputParser $parser = null,
        $columnsName = 'fields'
    ) {
        $primaryKey = $this->model->getKeyName();
        if (!$parser) {
            $parser = $this->makeInputParser($this->getInput());
        }

        return $parser->createColumnsList(
            $defColumns,
            $primaryKey,
            [],
            $columnsName
        );
    }

    /**
     * Get model columns list based on input
     *
     * @param  string  $columnsName
     *
     * @return array|string
     */
    public function getValidColumnsList($columnsName = 'fields')
    {
        return $this->getColumnsList(['*'], null, $columnsName);
    }

    /**
     * Apply conditions on the model
     *
     * @param  Object|Model|Builder  $model
     * @param  array  $conditions
     *
     * @return Object
     *
     * @throws InvalidArgumentException
     */
    protected function applyConditions($model, array $conditions)
    {
        foreach ($conditions as $cond) {
            $column = $cond['column'];
            $operator = $cond['operator'];
            $value = $cond['value'];

            if (!$cond['raw']) {
                if ($operator === '><') {
                    $model = $model->whereBetween($column, explode(',', $value));
                } else {
                    $model = $model->where($column, $operator, $value);
                }
            } else {
                $model = $model->whereRaw("$column $operator ?", [$value]);
            }
        }

        return $model;
    }

    /**
     * Apply selects on the model
     *
     * @param  Object  $model
     * @param  array|string  $columns
     *
     * @return Object
     */
    protected function applySelects($model, $columns)
    {
        if ($columns) {
            if (is_array($columns)) {
                if (!$this->rawColumns) {
                    return $model->select($columns);
                }

                return $model->selectRaw(implode(', ', $columns));
            }

            return $model->selectRaw($columns);
        }

        return $model;
    }

    /**
     * Apply sort on the model
     *
     * @param  Object  $model
     * @param  array  $order
     *
     * @return Object
     */
    protected function applySort($model, array $order)
    {
        foreach ($order as $k => $v) {
            if (is_int($k)) {
                $model = $model->orderBy($v);
            } else {
                $model = $model->orderBy($k, $v);
            }
        }

        return $model;
    }

    /**
     * @param  Builder|Object  $model
     *
     * @return int
     */
    protected function getCount($model, $columns = '*')
    {
//        $a = (int) $model->aggregate('count', Arr::wrap($columns));
//        dd($a);
//
        return $model->count();
    }

    /**
     * Paginate model data
     *
     * @param  Object  $model
     * @param  array|string  $columns
     * @param  array  $conditions
     * @param  array  $order
     * @param  int  $page
     * @param  int  $limit
     * @param  array  $specials
     *
     * @return Paginator
     *
     * @throws InvalidArgumentException
     */
    protected function paginateModel(
        $model,
        $columns,
        array $conditions,
        array $order,
        $page,
        $limit,
        array $specials = []
    ) {
        $model = $this->applyConditions($model, $conditions);

        /** @var Builder|Object $countModel */
        $countModel = $this->getCountModel();
        if ($this->withGroupBy) {
//            if(!$countModel) {
//                $countModel = $model;
//            }

//            $count = DB::table( DB::raw("({$countModel->toSql()}) as sub") )
//                ->mergeBindings($countModel->getQuery())
//                ->count();
            $count = 10000;
        } else {
            if ($countModel === null) {
                $count = $this->getCount($model);
            } else {
                $countModel = $this->applyConditions($countModel, $conditions);
                $count = $this->getCount($countModel);
            }
        }

        if ($count) {
            $model = $this->applySort(
                $this->applySelects($model, $columns),
                $order
            );
        }

        return $this->generatePaginatedData($count, $page, $limit, $model, $specials);
    }

    /**
     * Gets data manually from database (it makes 2 queries maximum
     * to database - one for count, one for getting data) and pass data to
     * paginator
     *
     * @param  int  $count
     * @param  int  $page
     * @param  int  $limit
     * @param  Object|Builder  $modelData
     * @param  array  $specials
     *
     * @return Paginator
     */
    protected function generatePaginatedData(
        $count,
        $page,
        $limit,
        $modelData,
        array $specials = []
    ) {
        $items = [];
        if ($count) {
            // calculating offset
            $offset = $this->calculateOffset($page, $limit);

            if ($offset < $count) {
                $modelData = $modelData->offset($offset);
                $modelData = $modelData->limit($limit);

                // getting data
                $items = $modelData->get();
            }
        }

        // passing data to paginator
        $paginator = new Paginator($items, $count, $limit, $page, [
            'path'  => $this->request->url(),
            'query' => $this->request->query(),
        ]);

        return $paginator;
    }

    /**
     * Returns real column name (for id, created_at, updated_at get column name
     * from database)
     *
     * @param  string  $name
     *
     * @return string
     */
    protected function getRealColumnName($name)
    {
        return $this->columnNameResolver->getRealColumnName($name);
    }

    /**
     * Return array of real DB column names for id, created_at, updated_at
     *
     * @return array
     */
    protected function getRealColumnNames()
    {
        return $this->columnNameResolver->getRealColumnNames();
    }

    /**
     * Calculates offset for query
     *
     * @param  int  $page
     * @param  int  $limit
     *
     * @return int
     */
    public function calculateOffset($page, $limit)
    {
        return ($page - 1) * $limit;
    }

    /**
     * Return list of items
     *
     * @param  string  $value
     * @param  string|null  $key
     *
     * @return mixed
     */
    public function pluck($value, $key = null)
    {
        $model = $this->getModel();

        return $model->pluck($value, $key)->all();
    }

    /**
     * Return Model count
     *
     * @return int
     */
    public function count()
    {
        $model = $this->getModel();

        return $model->count();
    }

    /**
     * Wrapper for show - finds the item and wraps output together with
     * frontend validation rules (for most modules there are no custom labels)
     *
     * @param  int  $id
     * @param  bool  $full
     *
     * @return array
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($id, $full = false)
    {
        $output['item'] = $this->find($id);

        if ($full) {
            $output['fields'] = $this->getRequestRules();
        }

        return $output;
    }

    /**
     * Function that gets request rules. We don't want to make it abstract
     * because not every repository will need it
     *
     * @return array
     */
    public function getRequestRules()
    {
        return [];
    }

    /**
     * Creates new repository
     *
     * @param  string  $repositoryName  Repository name without 'Repository' part
     * @param  string|null  $moduleName  Module name if it's different than
     *                                    Repository
     *
     * @return mixed
     */
    public function makeRepository($repositoryName, $moduleName = null)
    {
        if ($moduleName === null) {
            $moduleName = $repositoryName;
        }

        return $this->app->make("App\\Modules\\{$moduleName}\\Repositories\\{$repositoryName}Repository");
    }

    /**
     * Get repository - if repository has not been created yet, it will create
     * it and return. This method should be used if we use same repository in
     * more than one method but we don't want to inject repository in
     * constructor
     *
     * @param  string  $repositoryName
     * @param  string|null  $moduleName
     *
     * @return mixed
     */
    public function getRepository($repositoryName, $moduleName = null)
    {
        if ($moduleName === null) {
            $moduleName = $repositoryName;
        }

        $fullRepositoryName = $moduleName.'_'.$repositoryName;

        if (!isset($this->repositories[$fullRepositoryName])) {
            $this->repositories[$fullRepositoryName] = $this->makeRepository($repositoryName, $moduleName);
        }

        return $this->repositories[$fullRepositoryName];
    }

    /**
     * Get IP address
     *
     * @return string
     */
    public function getIpAddress()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * Update record with given data. It should NOT be used for data from
     * input (data are not validated) unless you manually specify $data array
     * keys
     *
     * @param  int|Model  $id
     * @param  array  $data
     *
     * @return Model
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function internalUpdate($id, array $data)
    {
        if ($id instanceof Model) {
            $object = $id;
        } else {
            $object = $this->model->findOrFail($id);
        }
        $object->forceFill($data);
        $object->save();

        return $this->find($object->getId());
    }

    /**
     * Get model for given person (all or only first)
     *
     * @param  int  $personId
     * @param  bool  $first
     *
     * @return Collection|Model
     */
    protected function findForPerson($personId, $first = false)
    {
        /** @var Builder|Model $query */
        $query = $this->model->where('person_id', $personId);

        if ($first) {
            return $query->first();
        }

        return $query->get();
    }

    /**
     * Adding needed table alias to $joinableTables array.
     *
     * @param $column
     */
    protected function joinTable($column)
    {
        $column = explode('.', $column);
        if (!in_array($column[0], $this->joinableTables)) {
            $this->joinableTables[] = $column[0];
        }
    }

    protected function setCustomColumns($model, $countWithoutJoins = false, $withGroupBy = false)
    {
        $this->withGroupBy = $withGroupBy;

        $input = $this->getInput();

        $this->setRawColumns(true);

        $select = [];

        if (method_exists($model, 'getTable')) {
            $tableName = $model->getTable();
        } else {
            $tableName = null;
        }

        if (Arr::has($input, 'fields')) {
            $columns = explode(',', $input['fields']);
            $columns[] = 'id';
            foreach ($columns as $key => $column) {
                if (Arr::has($this->availableColumns, $column)) {
                    $select[] = $this->addNewSelect($this->availableColumns[$column], $column, $model);
//                    unset($this->availableColumns[$column]);
                }
            }
        } else {
            foreach ($this->availableColumns as $column => $selectString) {
                if (!empty($this->availableColumns[$column])) {
                    $select[] = $this->addNewSelect($selectString, $column, $model);
                }
            }
        }

        $model = $model->selectRaw(implode(',', $select));

        if ($countWithoutJoins) {
            $countSelect = [];

            if ($tableName) {
                foreach ($select as $column) {
                    $tabCol = explode('.', $column);

                    if (count($tabCol) === 1 || (count($tabCol) === 2 && $tabCol[0] === $tableName)) {
                        $countSelect[] = $column;
                    }
                }
            }

            if (!count($countSelect)) {
                $countSelect = $select;
            }

            $countModel = $this->model->newInstance();
            $countModel = $countModel->selectRaw(implode(',', $countSelect));
            $this->setCountModel($countModel);
        }

        unset($input['fields']);
        $this->setInput($input);

        return $model;
    }

    /**
     * Creating new select.
     *
     * @param $select
     * @param $as
     * @param $model
     *
     * @return string
     */
    protected function addNewSelect($select, $as, &$model)
    {
        if (!empty($this->availableColumns[$as])) {
            $this->joinTable($select);
            if (!in_array($as, $this->illegalAliases)) {
                return $select.' as '.$as;
            } else {
                return $select;
            }
        }
    }

    /**
     * Setting custom sorts based on $availableColumns method,
     * you can sort by all columns in mentioned array.
     *
     *
     * @param  Model|Builder  $model
     *
     * @return Model|Builder
     */
    protected function setCustomSort($model, $availableColumns = [])
    {
        $input = $this->getInput();

        if (!count($availableColumns)) {
            $availableColumns = $this->availableColumns;
        }

        if (Arr::has($input, 'sort')) {
            $sorts = explode(',', $input['sort']);
            $method = 'ASC';

            foreach ($sorts as $key => $sort) {
                if ($sort[0] == "-") {
                    $sort = ltrim($sort, $sort[0]);
                    $method = 'DESC';
                }

                if (Arr::has($availableColumns, $sort)) {
                    $model = $model->orderBy(DB::raw($availableColumns[$sort]), $method);
                }

                unset($sorts[$key]);
            }

            unset($input['sort']);
            $this->setInput($input);
        }

        return $model;
    }

    /**
     * Set filters based on availableColumns method,
     * before start, make sure that needed filter name is in
     * availableColumns array
     *
     * @param  Model|Builder  $model
     *
     * @return Builder|Model
     */
    protected function setCustomFilters($model)
    {
        $inputs = $this->getInput();

        $customOperators = [
            ',',   //between
            '>=',
            '<=',
            '>',
            '<',
            '%%',
            '%',     //like
        ];

        foreach ($inputs as $inputName => $input) {
            if (Arr::has($this->availableColumns, $inputName)) {
                $column = $this->availableColumns[$inputName];
                $this->joinTable($column);
                foreach ($customOperators as $operator) {
                    if (strstr($input, $operator)) {
                        if (in_array($operator, $customOperators) && $operator != ',' && $operator != '%%') {
                            $model = $model->where(
                                DB::raw($column),
                                ($operator != '%') ? $operator : 'LIKE',
                                ($operator != '%') ? str_replace($operator, "", $input) : $input
                            );
                        } elseif ($operator == ',') {
                            $range = explode(',', $input);

                            if (count($range)) {
                                if ($inputName = 'received_date') {
                                    $receivedDates = $range;
                                    $receivedDates[0] = date('Y-m-d 00:00:00', strtotime($receivedDates[0]));
                                    $receivedDates[1] = date('Y-m-d 23:59:59', strtotime($receivedDates[1]));
                                    $range = $receivedDates;
                                }
                                $model = $model->whereBetween(
                                    DB::raw($column),
                                    $range
                                );
                            }
                        } elseif ($operator == '%%') {
                            $input = substr($input, 2);
                            $input = substr($input, 0, -2);

                            $model = $model->where(
                                DB::raw($column),
                                'REGEXP',
                                '[[:<:]]'.$input.'[[:>:]]'
                            );
                        }
                        $this->setCountModel($model);
                        unset($inputs[$inputName]);
                        continue 2;
                    }
                }

                $values = explode(';', $input);
                if (count($values)) {
                    if (count($values) == 1) {
                        $model = $model->where(
                            DB::raw($column),
                            '=',
                            $values[0]
                        );
                        $this->setCountModel($model);
                        unset($inputs[$inputName]);
                    }
                    if (count($values) > 1) {
                        $model = $model->where(
                            function ($query) use ($column, $values) {
                                foreach ($values as $value) {
                                    /** @var Query|Builder $query */
                                    $query = $query->orWhere(
                                        DB::raw($column),
                                        '=',
                                        $value
                                    );
                                }
                                return $query;
                            }
                        );

                        $this->setCountModel($model);
                        unset($inputs[$inputName]);
                    }
                }
            }
        }

        $this->setInput($inputs);
        return $model;
    }

    /**
     * Cast given fields to float
     *
     * @param       $item
     * @param  array  $fields
     *
     * @return mixed
     */
    protected function castFieldsToFloat($item, $fields = ['price', 'total', 'quantity'])
    {
        if ($fields) {
            foreach ($fields as $field) {
                if (!empty($item[$field])) {
                    $item[$field] = (float)$item[$field];
                }
            }
        }

        return $item;
    }

    public function removeEmptyProps($input)
    {
        foreach ($input as $key => $item) {
            if ($item == ' ' || $item == '' || $item == 'null' || is_null($item)) {
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * Check item based on date fields and if date is zero then set null else set date
     *
     * @param  array  $item
     * @param  array  $fields
     *
     * @return array
     */
    protected function setDateOrNull(array $item, array $fields)
    {
        foreach ($fields as $field) {
            if (isset($item[$field])) {
                $item[$field] = getDateOrNull($item[$field]);
            }
        }

        return $item;
    }

    /**
     * This is function for generating paginate from existing collection
     * eg. after $model = $model->get(), you can create pagination.
     *
     * @param $items
     * @param $perPage
     * @param  bool  $setDefaultOption
     * @param  array  $options
     *
     * @return Paginator
     */
    public function paginateFromCollection($items, $perPage, $setDefaultOption = true, $options = [])
    {
        if ($setDefaultOption) {
            $options = ['path' => request()->url(), 'query' => request()->query()];
        }
        $page = Request::get('page', 1); // Get the current page or default to 1

        $items = $items instanceof Collection ? $items : Collection::make($items);

        return new Paginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
