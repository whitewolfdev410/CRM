<?php

namespace App\Core\Old;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;

/**
 * Class ModelJoinTrait
 *
 * @package App\Core\Old
 *
 * Provide an easy mechanism to join the related table
 *
 * @method Builder|EloquentBuilder|Model|ModelJoinTrait matchManufacturer(string $manufacturer)
 */
trait ModelJoinTrait
{
    /**
     * This determines the foreign key relations automatically to prevent the need to figure out the columns.
     *
     * @param Builder $query
     * @param string  $relation_name
     * @param string  $operator
     * @param string  $type
     * @param bool    $where
     *
     * @return Builder
     */
    public function scopeWithBelongsTo($query, $relation_name, $operator = '=', $type = 'left', $where = false)
    {
        /** @var BelongsTo $relation */
        $relation = $this->$relation_name();
        $table = $relation->getRelated()->getTable();
        $one = $relation->getQualifiedParentKeyName();
        $two = $relation->getForeignKeyName();

        if (empty($query->columns)) {
            $query->select($this->getTable() . '.*');
        }
        foreach (\Schema::getColumnListing($table) as $related_column) {
            $query->addSelect(new Expression("`$table`.`$related_column` AS `$table.$related_column`"));
        }

        return $query->join($table, $one, $operator, $two, $type, $where); //->with($relation_name);
    }
}
