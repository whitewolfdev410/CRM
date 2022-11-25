<?php

namespace App\Core;

use App\Core\Exceptions\SqlPaginatorInvalidDataPassedException;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Config\Repository as Config;

/**
 * Class SqlPaginator - get data via Paginator for manual SQL
 *
 * @package App\Core
 */
class SqlPaginator
{
    /**
     * @var Container
     */
    private $app;

    /**
     * Data for counting records
     *
     * @var string
     */
    private $countData;

    /**
     * Data for getting data
     *
     * @var string
     */
    private $sqlData;

    /**
     * Request object
     *
     * @var Request
     */
    private $request;

    /**
     * Limit of data to get
     *
     * @var int
     */
    private $limit;

    /**
     * Number of current page
     *
     * @var int
     */
    private $page;

    /**
     * @var ColumnNameResolver
     */
    private $columnNameResolver;

    /**
     * @var QueryInputParser
     */
    protected $inputParser;

    /**
     * Class constructor
     *
     * @param Container $app
     * @param int $limit
     * @param array $searchable
     * @param array $sortable
     * @param array $sortableMap
     * @param ColumnNameResolver $columnNameResolver
     * @param array|null $input
     */
    public function __construct(
        Container $app,
        $limit,
        array $searchable,
        array $sortable,
        array $sortableMap,
        ColumnNameResolver $columnNameResolver,
        $input = null
    ) {
        $this->app = $app;
        $this->request = $app->make('Illuminate\Http\Request');
        $this->config = $app->make('Illuminate\Config\Repository');
        $this->limit = $limit;
        $this->columnNameResolver = $columnNameResolver;

        $_input = is_null($input) ? $this->request->query() : $input;

        $this->inputParser =
            $this->makeInputParser(
                $_input,
                $searchable,
                $sortable,
                $sortableMap
            );
    }

    protected function makeInputParser(
        array $input,
        array $searchable,
        array $sortable,
        array $sortableMap
    ) {
        return new QueryInputParser(
            $this->app,
            $input,
            $this->columnNameResolver,
            $searchable,
            $sortable,
            $sortableMap,
            true
        );
    }

    /**
     * Set count SQL and bindings.
     *
     * @param array $data
     */
    public function setCountSql(array $data)
    {
        $this->verifyData($data);
        $this->countData = $data;
    }

    /**
     * Set data SQL and bindings.
     *
     * @param array $data
     */
    public function setDataSql(array $data)
    {
        $this->verifyData($data);
        $this->sqlData = $data;
    }

    /**
     * Verifies if required keys exist in array
     *
     * @param array $data
     */
    private function verifyData($data)
    {
        if (!isset($data['sql'])
            || !isset($data['columns'])
        ) {
            throw $this->app->make(SqlPaginatorInvalidDataPassedException::class);
        }
    }

    /**
     * Generates count SQL query with bindings
     *
     * @return array
     */
    private function prepareCountSql()
    {
        $sql = 'SELECT ' . $this->countData['columns'] . ' AS `aggregate` ' .
            $this->countData['sql'];

        $bindings = [];
        if (isset($this->countData['bindings'])) {
            $bindings = $this->countData['bindings'];
        }

        [$where, $bindings] = $this->createWhereCondition(
            $this->countData,
            $bindings
        );

        $sql = $this->cleanSql($sql . ' ' . $where);

        return [$sql, $bindings];
    }

    /**
     * Generated where condition with bindings for query
     *
     * @param array $data
     * @param array $bindings
     *
     * @return array
     */
    private function createWhereCondition(array $data, array $bindings)
    {
        $whereConditions = [];
        if (isset($data['where']) && trim($data['where']) != '') {
            $whereConditions[] = ' ( ' . $data['where'] . ' ) ';
        }

        $conditions = $this->inputParser->createWhereConditions();

        foreach ($conditions as $cond) {
            $whereConditions[]
                = $cond['column'] . ' ' . $cond['operator'] . '?';
            $bindings[] = $cond['value'];
        }

        $where = '';
        if (count($whereConditions)) {
            $where = ' WHERE ' . implode(' AND ', $whereConditions);
        }

        return [$where, $bindings];
    }

    /**
     * Generated ORDER BY clause
     *
     * @param array $data
     *
     * @return string
     */
    private function createOrderClause(array $data)
    {
        $order = [];
        if (isset($data['order'])) {
            $order = $data['order'];
        }

        $orderList = $this->inputParser->createOrderByList($order);

        $orders = [];
        foreach ($orderList as $column => $dest) {
            $orders[] = $column . ' ' . $dest;
        }

        $orderClause = '';

        if (count($orders)) {
            $orderClause = ' ORDER BY ' . implode(', ', $orders);
        }

        return $orderClause;
    }

    /**
     * Generated column list for SQL query
     *
     * @param array $data
     *
     * @return array|string
     */
    private function createColumnSection(array $data)
    {
        $columns = [];
        if (isset($data['columns'])) {
            $columns = $data['columns'];
        }
        $primaryKey = $this->getRealColumnName('id');

        $columns = $this->inputParser->createColumnsList($columns, $primaryKey);

        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        return $columns;
    }

    /**
     * Creates data query with bindings
     *
     * @return array
     */
    protected function prepareDataSql()
    {
        $columns = $this->createColumnSection($this->sqlData);

        $sql = 'SELECT ' . $columns . ' ' . $this->sqlData['sql'];

        $bindings = [];

        if (isset($this->sqlData['bindings'])) {
            $bindings = $this->sqlData['bindings'];
        }

        [$where, $bindings] = $this->createWhereCondition(
            $this->sqlData,
            $bindings
        );

        $order = $this->createOrderClause($this->sqlData);

        $sql = $this->cleanSql($sql . ' ' . $where . ' ' . $order);

        return [$sql, $bindings];
    }

    /**
     * Gets data from database and wrap them in Paginator. It runs maximum
     * 2 queries (one for getting count and one for getting data). If there are
     * no records or number of curreng page is invalid it will run only one
     * query
     *
     * @return Paginator
     */
    public function getData()
    {
        [$sql, $bindings] = $this->prepareCountSql();
        $count = DB::select($sql, $bindings);
        $count = $count[0]->aggregate;

        // calculations for pagination
        $page = $this->inputParser->getCurrentPage();
        $limit = $this->inputParser->getLimit(
            $this->config->get('database.max_records'),
            $this->limit
        );

        $items = [];

        if ($count) {
            $items = $this->getRealData($count, $page, $limit);
        }

        // passing data to paginator
        $paginator = new Paginator($items, $count, $limit, $page, [
            'path' => $this->request->url(),
            'query' => $this->request->query(),
        ]);

        return $paginator;
    }

    /**
     * Gets real data. It calculates page and limit (based on given data and
     * request data), calculates offset and if offset is valid it launches SQL
     * query to get data from database
     *
     * @param int $count
     * @param int $page
     * @param int $limit
     *
     * @return array
     */
    private function getRealData($count, $page, $limit)
    {
        $items = [];

        // calculating offset
        $offset = $this->calculateOffset($page, $limit);

        if ($offset < $count) {
            [$sql, $bindings] = $this->prepareDataSql();
            $items = DB::select($sql . " LIMIT $offset, $limit", $bindings);
        }

        return $items;
    }

    /**
     * Cleans SQL query (only trims it)
     *
     * @param string $sql
     *
     * @return string
     */
    private function cleanSql($sql)
    {
        return trim($sql);
    }

    /**
     * Return real column name in table for $name
     *
     * @param string $name
     *
     * @return string
     */
    protected function getRealColumnName($name)
    {
        return $this->columnNameResolver->getRealColumnName($name);
    }

    /**
     * Calculates offset for query
     *
     * @param int $page
     * @param int $limit
     *
     * @return int
     */
    public function calculateOffset($page, $limit)
    {
        return ($page - 1) * $limit;
    }
}
