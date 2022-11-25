<?php

namespace App\Core;

use App\Core\Extended\BelongsToMany;
use App\Modules\WorkOrder\Services\WorkOrderNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Class LogModel
 *
 * Allow to log any create/update/delete actions when using model
 *
 * @package App
 *
 */
class LogModel extends Model
{
    /**
     * Saves history for create/update/delete actions for any model that inherits from this class
     */
    public static function boot()
    {
        parent::boot();

        // INSERT action
        static::created(function ($model) {
            $attributes = $model->getAttributes();

            $id = $attributes[$model->primaryKey];

            $data = [];

            foreach ($attributes as $k => $v) {
                if ($k == $model->primaryKey
                    || $k == $model::CREATED_AT
                    || $k == $model::UPDATED_AT
                ) {
                    continue;
                }
                $rec['columnname'] = $k;
                $rec['value_from'] = mb_substr($v, 0, 100);
                $rec['value_to'] = mb_substr($v, 0, 100);
                $data[] = $rec;
            }
            static::saveHistory('Insert', $model, $id, $data);
        });

        // UPDATE action
        static::updated(function ($model) {
            $attributes = $model->getDirty();
            $original = $model->getOriginal();

            // for some updated events it might be empty
            if (empty($original)) {
                return;
            }

            $id = $original[$model->primaryKey];

            $data = [];

            foreach ($attributes as $k => $v) {
                if ($k == $model::CREATED_AT
                    || $k == $model::UPDATED_AT
                ) {
                    continue;
                }

                $rec['columnname'] = $k;
                $rec['value_from'] = Arr::get($original, $k);
                $rec['value_to'] = mb_substr($v, 0, 100);
                $data[] = $rec;
            }
            static::saveHistory('Update', $model, $id, $data);
        });

        // DELETE action
        static::deleted(function ($model) {
            $attributes = $model->getAttributes();

            $id = $attributes[$model->primaryKey];

            $data = [];

            $rec['tablename'] = ''; // just to make $data not empty
            $data[] = $rec;

            static::saveHistory('Delete', $model, $id, $data);
        });
    }

    /**
     * Prepares data to insert and insert data into database
     * (without using model for better performance)
     *
     * @param string $action Action name
     * @param Model  $model  Model for which we log history
     * @param int    $id     Id of record inserted/updated/deleted
     * @param array  $data   Data that should be inserted into database
     */
    protected static function saveHistory($action, $model, $id, array $data)
    {
        // @todo  ignore 'gps_location' , 'history' and 'system_error'

        // @todo related records #2874

        $skipTables = [
            'api_sync',
            'gps_location',
            'work_order_live_action',
            'work_order_live_action_location',
            'work_order_live_action_to_order',
            'file_aws',
            'link_person_wo_stats',
        ];

        $tableName = $model->getTable();
        if (in_array($tableName, $skipTables)) {
            return true;
        }
        
        $personId = getCurrentPersonId();

        [$relatedTable, $relatedRecord] = self::getRelatedHistoryData($model);

        foreach ($data as &$rec) {
            if (array_key_exists('value_from', $rec)
                && ($rec['value_from'] === null)
            ) {
                $rec['value_from'] = '';
            }

            if (array_key_exists('value_to', $rec)
                && ($rec['value_to'] === null)
            ) {
                $rec['value_to'] = '';
            }

            $rec['person_id'] = $personId;
            $rec['tablename'] = $model->getTable();
            $rec['record_id'] = $id;
            $rec['action_type'] = $action;
            $rec['date_created'] = Carbon::now();
            $rec['related_tablename'] = $relatedTable;
            $rec['related_record_id'] = $relatedRecord;
            $rec['changes'] = '';
        }
        unset($rec);

        if ($data) {
            DB::table('history')->insert($data);
        }
    }

    /**
     * Gets data for related_tablename and  related_record_id
     *
     * @param Model $model Model for which we log history
     *
     * @return array
     */
    private static function getRelatedHistoryData($model)
    {
        $tableName = '';
        $recordId = 0;

        if (method_exists($model, 'getHistoryRelatedRecord')) {
            $result = $model->getHistoryRelatedRecord();

            if ($result) {
                $tableName = $result[0];
                $recordId = $result[1];
            }
        }

        return [$tableName, $recordId];
    }

    /**
     * Log creating records in pivot tables
     *
     * @param string $table
     * @param int    $id
     * @param array  $columns
     */
    public static function logPivotCreated($table, $id, array $columns)
    {
        $data = [];

        foreach ($columns as $k => $v) {
            $rec['columnname'] = $k;
            $rec['value_to'] = mb_substr($v, 0, 100);
            $rec['tablename'] = $table;
            $data[] = $rec;
        }

        static::saveSimpleHistory('Insert', $id, $data);
    }

    /**
     * Log updating records in pivot tables
     *
     * @param string $table
     * @param int    $id
     * @param array  $attributes
     * @param array  $oldAttributes
     */
    public static function logPivotUpdated(
        $table,
        $id,
        array $attributes,
        array $oldAttributes
    ) {
        $data = [];

        foreach ($attributes as $k => $v) {
            if (!isset($oldAttributes[$k])) {
                continue;
            }
            if ($oldAttributes[$k] != $v) {
                $rec['columnname'] = $k;
                $rec['value_from'] = $oldAttributes[$k];
                $rec['value_to'] = mb_substr($v, 0, 100);
                $rec['tablename'] = $table;
                $data[] = $rec;
            }
        }

        if ($data) {
            static::saveSimpleHistory('Update', $id, $data);
        }
    }

    /**
     * Log deleting records in pivot tables
     *
     * @param  string $table
     * @param array   $ids
     */
    public static function logPivotDeleted($table, array $ids)
    {
        foreach ($ids as $id) {
            $data = [];
            $rec['tablename'] = $table;
            $data[] = $rec;

            static::saveSimpleHistory('Delete', $id, $data);
        }
    }


    /**
     * Prepares data to insert and insert data into database for pivots
     *
     * @param string $action Action name
     * @param int    $id     Id of record inserted/updated/deleted*
     * @param array  $data   Data that should be inserted into database
     */
    protected static function saveSimpleHistory($action, $id, array $data)
    {
        $personId = getCurrentPersonId();

        foreach ($data as $key => &$rec) {
            if (array_key_exists('value_from', $rec)
                && $rec['value_from'] === null
            ) {
                $rec['value_from'] = '';
            }
            if (array_key_exists('value_to', $rec)
                && $rec['value_to'] === null
            ) {
                $rec['value_to'] = '';
            }

            $rec['person_id'] = $personId;
            $rec['action_type'] = $action;
            $rec['date_created'] = Carbon::now();
            $rec['record_id'] = $id;

            if (isset($rec['columnname'])
                && in_array($rec['columnname'], [
                    'created_at',
                    'updated_at',
                    'date_created',
                    'date_modified',
                    'created_date',
                    'modified_date',
                ])
            ) {
                unset($data[$key]);
            }
        }
        unset($rec);

        if ($data) {
            DB::table('history')->insert($data);
        }
    }

    /**
     * Define a many-to-many relationship.
     * {@inheritdoc} No single change in this function - we just want to get BelongsToMany object from other namespace
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany(
        $related,
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null
    ) {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        return $this->newBelongsToMany(
            $instance->newQuery(),
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Instantiate a new BelongsToMany relationship.
     * {@inheritdoc} No single change in this function - we just want to get BelongsToMany object from other namespace
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relationName
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new BelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName
        );
    }
}
