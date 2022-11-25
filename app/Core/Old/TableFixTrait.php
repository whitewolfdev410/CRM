<?php

namespace App\Core\Old;

/**
 * Class TableFixTrait
 *
 * @package App\Core\Old
 *
 * Provide an easy mechanism to change id and date fields to the following keys:
 * id, created_at, updated_at. It will be used for returning objects as array
 * or json.
 *
 * It also creates 3 basic getters (and also accessor) for id, created_at and
 * updated_at fields*
 */
trait TableFixTrait
{
    /**
     * Creates list of append and hidden fields depending of actual class needs
     *
     * @param array $attributes
     */
    public function initTableFix(array $attributes = [])
    {
        parent::__construct($attributes);

        if ($this->primaryKey != 'id') {
            $this->appends[] = 'id';
            //$this->hidden[] = $this->primaryKey;
        }

        if (static::CREATED_AT != 'created_at') {
            $this->appends[] = 'created_at';
            $this->hidden[] = static::CREATED_AT;
        }

        if (static::UPDATED_AT != 'updated_at' && static::UPDATED_AT != '') {
            $this->appends[] = 'updated_at';
            $this->hidden[] = static::UPDATED_AT;
        }
    }

    /**
     * Accessor for id
     *
     * @return mixed
     */
    public function getIdAttribute()
    {
        return $this->attributes[$this->primaryKey];
    }

    /**
     * Accessor for created_at
     *
     * @return mixed
     */
    public function getCreatedAtAttribute()
    {
        return $this->attributes[static::CREATED_AT];
    }

    /**
     * Accessor for updated_at
     *
     * @return mixed
     */
    public function getUpdatedAtAttribute()
    {
        return $this->attributes[static::UPDATED_AT];
    }

    /**
     * Getter for id
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Getter for created_at
     *
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Getter for updated_at
     *
     * @return mixed
     */
    public function getUpdatedAt()
    {
        return $this->updated_at;
    }

    /**
     * Defines how the object should be converted to array
     *
     * @return mixed
     */
    public function toArray()
    {
        $this->hideNotExistingAttributes();

        return parent::toArray();
    }

    /**
     * Removes from appended data created_at and updated_at if there are no
     * real columns in data set (user defined columns in query)
     */
    protected function hideNotExistingAttributes()
    {
        if (!isset($this->attributes[static::CREATED_AT])) {
            $this->appends = array_diff($this->appends, ['created_at']);
        }
        if (!isset($this->attributes[static::UPDATED_AT])) {
            $this->appends = array_diff($this->appends, ['updated_at']);
        }
        if (!isset($this->attributes[$this->primaryKey])) {
            $this->appends = array_diff($this->appends, ['id']);
        }
    }
}
