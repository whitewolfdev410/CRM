<?php

namespace App\Core;

use Illuminate\Support\Str;
use Illuminate\Container\Container;

class QueryInputParser
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var DbConfig
     */
    protected $dbConfig;

    /**
     * @var array
     */
    protected $input;

    /**
     * @var array
     */
    protected $specials;

    /**
     * @var ColumnNameResolver
     */
    protected $columnNameResolver;

    /**
     * @var array
     */
    protected $searchable;

    /**
     * @var array
     */
    protected $sortable;

    /**
     * @var bool
     */
    protected $rawColumns = false;
    /**
     * @var array
     */
    private $sortableMap;

    /**
     * Custom operators that might be used for comparison (at the moment only
     * for id field). Be aware - order here is important - longer operators that
     * contains shorter ones should be at the beginning
     *
     * @var array
     */
    protected $customOperators = ['>=', '<=', '><', '>', '<',];

    /**
     * Constructor
     *
     * @param Container          $app
     * @param array              $input
     * @param ColumnNameResolver $columnNameResolver
     * @param array              $searchable Fields that will be used from request for
     *                                       filtering
     * @param array              $sortable   Fields that will be used for sorting
     * @param array              $sortableMap
     * @param bool               $rawColumns Whether to use raw columns. You should use it
     *                                       only if you are 100% sure of where data coming from (for example set
     *                                       manually in script), otherwise you risk SQL injection
     */
    public function __construct(
        Container $app,
        array $input,
        ColumnNameResolver $columnNameResolver,
        array $searchable = [],
        array $sortable = [],
        array $sortableMap = [],
        $rawColumns = false
    ) {
        $this->app = $app;
        $this->dbConfig = $app->make(DbConfig::class);
        $this->columnNameResolver = $columnNameResolver;
        $this->searchable = $searchable;
        $this->sortable = $sortable;
        $this->sortableMap = $sortableMap;
        $this->rawColumns = $rawColumns;
        [$this->input, $this->specials] = static::separateInputSpecials($input);
    }

    public function getInput()
    {
        return $this->input;
    }

    public function getSpecials()
    {
        return $this->specials;
    }

    /**
     * Separates special query parameters from input
     *
     * @param array $input
     *
     * @return array
     */
    public static function separateInputSpecials($input)
    {
        $specials = [
            'page'   => false,
            'limit'  => false,
            'sort'   => false,
            'fields' => false,
        ];

        $_input = $input;

        // special parameters to $specials array
        foreach ($specials as $k => $v) {
            if (isset($_input[$k])) {
                $specials[$k] = trim($_input[$k]);
                unset($_input[$k]);
            }
        }

        return [$_input, $specials];
    }

    /**
     * Generated conditions that will be used to query
     *
     * @param array  $conditions
     * @param string $table
     * @param string $column
     *
     * @return array
     */
    public function createWhereConditions(
        array $conditions = [],
        $table = '',
        $column = ''
    ) {
        foreach ($this->input as $k => $v) {
            $orgColumnName = $k;
            $k = $this->getRealColumnName($k);
            if (in_array($k, $this->searchable)) {
                $rec = [];

                if ($k === 'person_name') {
                    $collation = $this->dbConfig->get('collation');

                    $rec['column'] = "person_name({$table}.{$column}) COLLATE {$collation} ";
                    $rec['raw'] = true;
                } else {
                    $rec['column'] = $k;
                    $rec['raw'] = false;
                }

                // use custom operators if possible, otherwise = operator is used
                if (/*($orgColumnName == 'id') && */
                Str::startsWith($v, $this->customOperators)
                ) {
                    $operator = null;
                    foreach ($this->customOperators as $op) {
                        if (Str::startsWith($v, $op)) {
                            $operator = $op;
                            break;
                        }
                    }

                    $rec['operator'] = $operator;
                    $v = mb_substr($v, mb_strlen($operator));
                } else {
                    $rec['operator'] = '=';
                }
                if (strpos($v, '%') !== false) {
                    $rec['operator'] = 'LIKE';
                }
                $rec['value'] = trim($v);
                $conditions[] = $rec;
            }
        }

        return $conditions;
    }

    /**
     * Generated order by list that will be used in ORDER BY query
     *
     * @param array $defOrder
     * @param array $order
     *
     * @return array
     */
    public function createOrderByList(
        array $defOrder = [],
        array $order = []
    ) {
        if (isset($this->specials['sort']) && $this->specials['sort']) {
            $sort = explode(',', $this->specials['sort']);

            foreach ($sort as $sortFlag) {
                $dest = 'ASC';
                $column = $sortFlag;
                if (0 === strpos($sortFlag, '-')) {
                    $dest = 'DESC';
                    $column = substr($sortFlag, 1);
                }
                $column = $this->getRealColumnName($column);
                if (in_array($column, $this->sortable)) {
                    if (isset($this->sortableMap[$column])) {
                        $column = $this->sortableMap[$column];
                    }
                    $order[$column] = $dest;
                }
            }
        } elseif (count($defOrder)) {
            foreach ($defOrder as $k => $v) {
                if (is_int($k)) {
                    $dest = 'ASC';
                    $column = $v;
                } else {
                    $dest = $v;
                    $column = $k;
                }
                $order[$column] = $dest;
            }
        }

        return $order;
    }

    /**
     * Generates column list for query
     *
     * @param array|string $defColumns
     * @param string       $primaryKey
     * @param array        $columns
     * @param string       $columnsName
     *
     * @return array|string
     */
    public function createColumnsList(
        $defColumns,
        $primaryKey,
        array $columns = [],
        $columnsName = 'fields'
    ) {
        if (!isset($this->specials[$columnsName])
            && isset($this->input[$columnsName])
        ) {
            $this->specials[$columnsName] = $this->input[$columnsName];
            unset($this->input);
        }

        if (isset($this->specials[$columnsName])
            && $this->specials[$columnsName]
        ) {
            $columns = [];
            $fields = explode(',', $this->specials[$columnsName]);
            foreach ($fields as $field) {
                $field = $this->getRealColumnName($field);
                if (in_array($field, $this->searchable)) {
                    $columns[] = $field;
                }
            }

            // primary key must be always on columns list
            if (!in_array($primaryKey, $columns)) {
                // now we look if primary key is not used together with table
                // name - if yes, we won't need to add it to columns
                $found = false;
                foreach ($columns as $singleColumn) {
                    if (Str::endsWith($singleColumn, '.' . $primaryKey)) {
                        $found = true;
                        break;
                    }
                }

                // primary key not found
                if (!$found) {
                    $columns[] = $primaryKey;
                }
            }
        } else {
            // allowing raw queries for MySQL functions
            if ($this->rawColumns) {
                if (is_array($defColumns)) {
                    $columns = implode(', ', array_unique(array_merge($columns, $defColumns)));
                } else {
                    $columns[] = $defColumns;
                    $columns = implode(', ', $columns);
                }
            } else {
                $columns = array_unique(array_merge($columns, $defColumns));
            }
        }

        return $columns;
    }

    /**
     * Get current page number
     *
     * @return int
     */
    public function getCurrentPage()
    {
        $page = 1;
        if (isset($this->specials['page'])
            && $this->specials['page'] !== false
        ) {
            $page = (int)trim($this->specials['page']);
            if ($page < 1) {
                $page = 1;
            }
        }

        return $page;
    }

    /**
     * Get records limit for query
     *
     * @param int $maxRecords
     * @param int $defaultLimit
     *
     * @return int
     */
    public function getLimit($maxRecords, $defaultLimit)
    {
        $limit = $defaultLimit;

        if (isset($this->specials['limit'])
            && $this->specials['limit'] !== false
        ) {
            if ($this->specials['limit'] < $maxRecords) {
                $limit = (int)$this->specials['limit'];
            } else {
                $limit = $maxRecords;
            }
            if ($limit < 1) {
                $limit = $defaultLimit;
            }
        }

        return $limit;
    }

    /**
     * Return real column name in table for $name
     *
     * @param string $name
     *
     * @return mixed
     */
    protected function getRealColumnName($name)
    {
        return $this->columnNameResolver->getRealColumnName($name);
    }
}
