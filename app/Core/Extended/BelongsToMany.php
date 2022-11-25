<?php

namespace App\Core\Extended;

use App\Core\LogModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as BaseBelongsToMany;

/**
 * Class BelongsToMany
 *
 * @package App\Core\Extended
 *
 * Allows to log sync and detach for pivot tables
 *
 */
class BelongsToMany extends BaseBelongsToMany
{
    /**
     * {@inheritdoc}
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        // Here we will insert the attachment records into the pivot table. Once we have
        // inserted the records, we will touch the relationships if necessary and the
        // function will return. We can parse the IDs before inserting the records.
        $query = $this->newPivotStatement();

        $columns = $this->formatAttachRecords(
            $this->parseIds($id),
            $attributes
        );

        if (isset($columns[0])) {
            for ($i = 0, $c = count($columns); $i < $c; ++$i) {
                $recordId = $query->insertGetId($columns[$i]);

                if ($touch) {
                    $this->touchIfTouching();
                }

                LogModel::logPivotCreated(
                    $this->getTable(),
                    $recordId,
                    $columns[$i]
                );
            }
        } else {
            $recordId = $query->insertGetId($columns);
            if ($touch) {
                $this->touchIfTouching();
            }

            LogModel::createPivot($this->getTable(), $recordId, $columns);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        $orgAttributes = $attributes;

        if (in_array($this->updatedAt(), $this->pivotColumns)) {
            $attributes = $this->addTimestampsToAttachment($attributes, true);
        }

        $query = $this->newPivotStatementForId($id);

        $getQuery = clone ($query);
        $getQuery = $getQuery->first();

        // @TODO need to check if attributes have to be casted before assigning to $orgAttributes
        $updated = $query->update(
            $this->castAttributes($attributes)
        );

        if ($touch) {
            $this->touchIfTouching();
        }

        if ($updated) {
            $getQuery = (array)$getQuery;
            $primaryKeyName = array_keys($getQuery)[0];

            LogModel::logPivotUpdated(
                $this->getTable(),
                $getQuery[$primaryKeyName],
                $orgAttributes,
                $getQuery
            );
        }

        return $updated;
    }

    /**
     * {@inheritdoc}
     */
    public function detach($ids = [], $touch = true)
    {
        $query = $this->newPivotQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        if (! is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            $query->whereIn($this->relatedPivotKey, (array) $ids);
        }

        // Getting ids of records that are about to be deleted
        $selQuery = clone $query;
        $recordIdsObject = (array)$selQuery->get();
        $recordIds = [];

        if ($recordIdsObject && isset($recordIdsObject[0])) {
            $primaryKeyName = array_keys((array)$recordIdsObject[0])[0];

            foreach ($recordIdsObject as $obj) {
                $recordIds[] = $obj->$primaryKeyName;
            }
        }

        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table. Then, if the touch parameter
        // is true, we will go ahead and touch all related models to sync.
        $results = $query->delete();

        if ($touch) {
            $this->touchIfTouching();
        }

        LogModel::logPivotDeleted($this->getTable(), $recordIds);

        return $results;
    }
}
