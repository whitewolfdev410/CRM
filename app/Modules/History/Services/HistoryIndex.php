<?php

namespace App\Modules\History\Services;

use App\Core\ColumnNameResolver;
use App\Core\SqlPaginator;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * Class HistoryIndex
 *
 * Prepares SQL to generate index data
 *
 * @package App\Modules\History\Services
 */
class HistoryIndex
{
    /**
     * @var Container
     */
    private $app;

    /**
     * Request
     *
     * @var Request
     */
    private $request;

    /**
     * Config
     *
     * @var Config
     */
    private $config;

    /**
     * Default limit of records to get
     *
     * @var int
     */
    private $limit;

    /**
     * Input
     *
     * @var array string
     */
    private $input;

    /**
     * Fields that will be used from request for filtering and sorting
     *
     * @var array
     */
    private $searchable;

    /**
     * @var ColumnNameResolver
     */
    private $columnNameResolver;

    /**
     * Class constructor
     *
     * @param Container          $app
     * @param int                $limit
     * @param array              $searchable
     * @param array              $sortable
     * @param array              $sortableMap
     * @param ColumnNameResolver $columnNameResolver
     */
    public function __construct(
        Container $app,
        $limit,
        array $searchable,
        array $sortable,
        array $sortableMap,
        ColumnNameResolver $columnNameResolver
    ) {
        $this->app = $app;
        $this->request = $app->make(Request::class);
        $this->config = $app->make(Config::class);
        $this->limit = $limit;

        $this->input = $this->request->query();

        $this->searchable = $searchable;
        $this->sortable = $sortable;
        $this->sortableMap = $sortableMap;
        $this->columnNameResolver = $columnNameResolver;
    }

    /**
     * Gets data using paginator
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getData()
    {
        $paginatorData = $this->generateQueries();

        $paginator = new SqlPaginator(
            $this->app,
            $this->limit,
            $this->searchable,
            $this->sortable,
            $this->sortableMap,
            $this->columnNameResolver,
            $this->input
        );

        if (isset($paginatorData['count'])) {
            $paginator->setCountSql($paginatorData['count']);
        }
        if (isset($paginatorData['data'])) {
            $paginator->setDataSql($paginatorData['data']);
        }

        return $paginator->getData();
    }

    /**
     * Prepares data for Paginator
     *
     * @param string $where1
     * @param array  $bindings1
     * @param string $where2
     * @param array  $bindings2
     *
     * @return array
     */
    public function preparePaginatorData(
        $where1,
        array $bindings1,
        $where2,
        array $bindings2
    ) {
        $columns
            = 'history.history_id AS id, history.person_id, history.tablename,
               history.record_id, history.related_tablename,
               history.related_record_id, history.columnname,
               history.value_from, history.value_to, history.action_type,
               history.date_created as `created_at`,
               person.custom_1, person.custom_3';

        $where = '';

        if ($where1 != '1' && $where2 != '1') {
            $where = "$where1 OR $where2";
        } elseif ($where1 != '1') {
            $where = $where1;
        } elseif ($where2 != '1') {
            $where = $where2;
        }

        return [
            'count' => [
                'sql'      => 'FROM history',
                'columns'  => 'count(history_id)',
                'where'    => $where,
                'bindings' => array_merge($bindings1, $bindings2),
            ],
            'data'  => [
                'sql'      => 'FROM history LEFT JOIN person
                     ON person.person_id = history.person_id',
                'columns'  => $columns,
                'order'    => ['history_id' => 'DESC'],
                'where'    => $where,
                'bindings' => array_merge($bindings1, $bindings2),
            ],
        ];
    }

    /**
     * Sets condition and bindings depending on data from request and launches
     * generateSqlQueries to prepare SQL data with bindings
     *
     * @return array
     */
    public function generateQueries()
    {
        $specInputs = ['multi_option', 'multiple'];

        $multi = $this->request->input('multi_option', '');

        $multiple = $this->request->input('multiple', '');

        $bindings = [];

        if ($multi) {
            $innerSQL = [];
            $innerSQL2 = [];

            /** @var array $recordIds */
            $recordIds = $this->request->input('record_ids', []);
            foreach ($recordIds as $table => $ids) {
                $bindings[] = $table;
                $ids = explode(',', $ids);
                $ids = array_map('intval', $ids);
                $bindings = array_merge($bindings, $ids);
                $ids_binding = implode(', ', array_fill(0, count($ids), '?'));

                $innerSQL[]
                    = '(tablename= ? and record_id IN (' . $ids_binding . '))';
                $innerSQL2[]
                    = '(related_tablename= ? and related_record_id IN ('
                    . $ids_binding . '))';
            }
            if (count($innerSQL)) {
                $where1 = '(' . implode(' OR ', $innerSQL) . ' )';
                $where2 = '(' . implode(' OR ', $innerSQL2) . ' )';
            } else {
                $where1 = 1;
                $where2 = 1;
            }
            $specInputs[] = 'record_ids';
        } elseif ($multiple == 'true') {
            $bindings[] = $this->request->input('tablename', '');
            $ids = explode(',', $this->request->input('record_id', []));
            $ids = array_map('intval', $ids);
            $bindings = array_merge($bindings, $ids);
            $ids_binding = implode(', ', array_fill(0, count($ids), '?'));

            $where1 = '(tablename= ? and record_id IN (' . $ids_binding . '))';
            $where2 = '(related_tablename= ? and related_record_id IN ('
                . $ids_binding . '))';
            $specInputs[] = 'tablename';
            $specInputs[] = 'record_id';
        } else {
            $tablename = trim($this->request->input('tablename', ''));
            $record_id = intval(trim($this->request->input('record_id', '')));

            $related_tablename = trim($this->request->input('related_tablename', ''));
            $related_record_id = intval(trim($this->request->input('related_record_id', '')));
            
            $where1 = [];
            $where2 = [];

            if ($related_tablename && $related_record_id) {
                if ($tablename != '') {
                    $where1[] = ' tablename = ? ';
                    $bindings[] = $tablename;
                }
                
                $where1[] = ' related_tablename = ? ';
                $bindings[] = $related_tablename;

                $where1[] = ' related_record_id = ? ';
                $bindings[] = $related_record_id;

                $where = '( ' . implode(' AND ', $where1) . ' )';
                
                if ($tablename && $tablename === 'file' && $related_tablename === 'work_order') {
                    $linkPersonWoIds = app(LinkPersonWoRepository::class)->getIdsForWo($related_record_id);
                    
                    if ($linkPersonWoIds) {
                        $whereOr = '(tablename = ? AND related_tablename = ? AND related_record_id in (' . implode(',', $linkPersonWoIds) . '))';
                        $bindings[] = 'file';
                        $bindings[] = 'link_person_wo';
                        
                        $where = '(' . $where . ' OR ' . $whereOr . ')';
                    }
                }
                
                return $this->preparePaginatorData(
                    $where,
                    $bindings,
                    1,
                    []
                );
            } else {
                if ($tablename != '') {
                    $where1[] = ' tablename = ? ';
                    $where2[] = 'related_tablename = ?';
                    $bindings[] = $tablename;
                }
                if ($record_id != '') {
                    $where1[] = ' record_id = ? ';
                    $where2[] = 'related_record_id = ?';
                    $bindings[] = $record_id;
                }
            }
            
            if (count($where1)) {
                $where1 = '( ' . implode(' AND ', $where1) . ' )';
            } else {
                $where1 = 1;
            }

            if (count($where2)) {
                $where2 = '( ' . implode(' AND ', $where2) . ' )';
            } else {
                $where2 = 1;
            }
            
            $specInputs[] = 'tablename';
            $specInputs[] = 'record_id';
        }

        foreach ($specInputs as $in) {
            if (isset($this->input[$in])) {
                unset($this->input[$in]);
            }
        }

        return $this->preparePaginatorData(
            $where1,
            $bindings,
            $where2,
            $bindings
        );
    }
}
