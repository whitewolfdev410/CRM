<?php

namespace App\Modules\History\Repositories;

use App\Core\AbstractRepository;
use App\Modules\History\Models\History;
use App\Modules\History\Services\HistoryIndex;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\Paginator;

/**
 * History repository class
 */
class HistoryRepository extends AbstractRepository
{
    protected $searchable = [];

    /* Data collection querying not available for history

    protected $searchable
        = [
            'history_id',
            'person_id',
            'tablename',
            'record_id',
            'related_tablename',
            'related_table_id',
            'columnname',
            'value_from',
            'value_to',
            'action_type',
            'date_created',
            'changes',
        ];
    */

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param History   $history
     */
    public function __construct(Container $app, History $history)
    {
        parent::__construct($app, $history);
    }

    /**
     * Pagination of results
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        $historyIndex = new HistoryIndex(
            $this->app,
            $perPage,
            $this->searchable,
            isset($this->sortable) ? $this->sortable : $this->searchable,
            $this->sortableMap,
            $this->columnNameResolver
        );

        $data = $historyIndex->getData();

        $input = $this->getInput('map', false);
        if (!empty($input['map'])) {
            $data = $data->toArray();
            $data['data'] = $this->mapData($data['data']);
        }

        return $data;
    }

    /**
     * Get person name and date created for last record for $recordId where
     * $column value has been changed to $valueTo
     *
     * @param string $table
     * @param int    $recordId
     * @param string $column
     * @param string $valueTo
     *
     * @return mixed
     */
    public function getRecordColumnValueToLastHistory(
        $table,
        $recordId,
        $column,
        $valueTo
    ) {
        return $this->model
            ->selectRaw('person_name(person_id) AS person_name, date_created')
            ->where('tablename', $table)
            ->where('record_id', $recordId)
            ->where('columnname', $column)
            ->where('value_to', $valueTo)
            ->orderByDesc('date_created')
            ->first();
    }

    /**
     * Get data from history for column in table with given record id
     *
     * @param string     $table
     * @param int        $recordId
     * @param string     $column
     * @param int|string $valueFrom
     * @param string     $valueFromOperator
     * @param int|string $valueTo
     * @param string     $valueToOperator
     * @param array      $fields
     * @param string     $actionType
     * @param array      $order
     * @param bool       $firstOnly
     *
     * @return mixed
     */
    public function getRecordColumnHistory(
        $table,
        $recordId,
        $column,
        $valueFrom,
        $valueFromOperator = '=',
        $valueTo,
        $valueToOperator = '=',
        array $fields = ['*'],
        $actionType = 'update',
        array $order = [],
        $firstOnly = false
    ) {
        $query = $this->model
            ->selectRaw(implode(', ', (array)$fields))
            ->where('tablename', $table)
            ->where('record_id', $recordId)
            ->where('columnname', $column)
            ->where('value_from', $valueFromOperator, $valueFrom)
            ->where('value_to', $valueToOperator, $valueTo)
            ->where('action_type', $actionType);

        if ($order) {
            foreach ($order as $column => $orderBy) {
                $query = $query->orderBy($column, $orderBy);
            }
        }

        if ($firstOnly) {
            return $query->first();
        }

        return $query->get();
    }

    /**
     * Get history fields and person name for last record for $recordId
     *
     * @param string $table
     * @param int    $recordId
     * @param string $column
     *
     * @return mixed
     */
    public function getRecordColumnLastHistory(
        $table,
        $recordId,
        $column
    ) {
        return $this->model
            ->selectRaw('history.*, person_name(person_id) AS person_name')
            ->where('tablename', $table)
            ->where('record_id', $recordId)
            ->where('columnname', $column)
            ->orderByDesc('history_id')
            ->first();
    }

    public function mapData($data)
    {
        return array_map(function ($item) {
            if (preg_match('/(type|person)_id$/', $item->columnname, $match)) {
                $type = $match[1];

                if (!empty($item->value_from)) {
                    $item->value_from = $this->getValue($type, $item->value_from);
                };

                if (!empty($item->value_from)) {
                    $item->value_to = $this->getValue($type, $item->value_to);
                };
            }

            if ($item->tablename === 'link_person_wo') {
                $item->tablename = $this->getValueForLinkPersonWo($item->record_id);
            }
            
            return $item;
        }, $data);
    }

    private static $cache = [];

    private function getValue($type, $id)
    {
        if (!isset(self::$cache[$type][$id])) {
            $name = null;
            
            switch ($type) {
                case 'person':
                    $personM = app(Person::class)->find($id);
                    if ($personM) {
                        $name = $personM->getName();
                    }
                    
                    break;
                case 'type':
                    $typeM = app(Type::class)->find($id);
                    if ($typeM) {
                        $name = $typeM->getTypeValue();
                    }
                    
                    break;
                default:
                    
            }

            if ($name) {
                self::$cache[$type][$id] = $name . ' (' . $id . ')';
            } else {
                self::$cache[$type][$id] = $id;
            }
        }

        return self::$cache[$type][$id];
    }

    private function getValueForLinkPersonWo($id)
    {
        $type = 'link_person_wo';

        if (!isset(self::$cache[$type][$id])) {
            $name = null;

            /** @var LinkPersonWo $linkPersonWo */
            $linkPersonWo = app(LinkPersonWo::class)->find($id);
            $personId = $linkPersonWo->getPersonId();

            if (!isset(self::$cache['person'][$personId])) {
                $personM = app(Person::class)->find($personId);
                if ($personM) {
                    $name = $personM->getName() . ' (' . $personId . ')';
                }

                self::$cache['person'][$personId] = $personId;
            } else {
                $name = self::$cache['person'][$personId];
            }

            if ($name) {
                self::$cache[$type][$id] = $type . ' - ' . $name;
            } else {
                self::$cache[$type][$id] = $type;
            }
        }

        return self::$cache[$type][$id];
    }
}
