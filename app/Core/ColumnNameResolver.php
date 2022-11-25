<?php

namespace App\Core;

class ColumnNameResolver
{
    protected $model;

    /**
     * Constructor
     *
     * @param object $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Returns real column name (for id, created_at, updated_at get column name
     * from database)
     *
     * @param string $name
     *
     * @return string
     */
    public function getRealColumnName($name)
    {
        if (strtolower($name) == 'id') {
            return $this->model->getKeyName();
        } elseif (strtolower($name) == 'created_at') {
            $m = $this->model;
            $created = $m::CREATED_AT;
            if ($created != '') {
                return $created;
            }
        } elseif (strtolower($name) == 'updated_at') {
            $m = $this->model;
            $updated = $m::UPDATED_AT;
            if ($updated != '') {
                return $updated;
            }
        }

        // @TODO Check if '/' does not get into conflict with anything
        return str_replace('/', '.', $name);
    }

    /**
     * Return array of real DB column names for id, created_at, updated_at
     *
     * @return array
     */
    public function getRealColumnNames()
    {
        $array = ['id' => '', 'created_at' => '', 'updated_at' => ''];
        foreach ($array as $k => $v) {
            $array[$k] = $this->getRealColumnName($k);
        }

        return $array;
    }
}
