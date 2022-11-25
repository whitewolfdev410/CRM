<?php

namespace App\Modules\Type\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class Type
 *
 * @property string color
 * @property int    orderby
 * @property int    sub_type_id
 * @property string type
 * @property int    type_id
 * @property string type_key
 * @property string type_value
 *
 * @package App\Modules\Type\Models
 */
class Type extends LogModel
{
    use TableFixTrait;

    const DISPATCHERS_TYPE_KEY = 'person.dispatchers';

    protected $table = 'type';

    protected $primaryKey = 'type_id';

    protected $fillable
        = [
            'type',
            'type_key',
            'type_value',
            'sub_type_id',
            'color',
            'orderby',
        ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region relationships

    /**
     * One-to-many relation with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function persons()
    {
        return $this->hasMany(\App\Modules\Person\Models\Person::class, 'type_id');
    }

    /**
     * One-to-many relation with Address
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function addresses()
    {
        return $this->hasMany(
            \App\Modules\Address\Models\Address::class,
            'type_id'
        );
    }

    /**
     * One-to-many relation with Contact
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function contacts()
    {
        return $this->hasMany(
            \App\Modules\Contact\Models\Contact::class,
            'type_id'
        );
    }

    /**
     * One-to-many relation with Activity
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activities()
    {
        return $this->hasMany(
            \App\Modules\Activity\Models\Activity::class,
            'type_id'
        );
    }

    /**
     * One-to-many relation with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subTypes()
    {
        return $this->hasMany(__CLASS__, 'sub_type_id');
    }

    /**
     * Many to one relation with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parentType()
    {
        return $this->belongsTo(__CLASS__, 'sub_type_id');
    }

    //endregion

    //region accessors

    /**
     * Get Type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Return type key
     *
     * @return string
     */
    public function getTypeKey()
    {
        return $this->type_key;
    }

    /**
     * Get Type value
     *
     * @return string
     */
    public function getTypeValue()
    {
        return $this->type_value;
    }

    /**
     * Get Subtype id field
     *
     * @return int
     */
    public function getSubTypeId()
    {
        return $this->sub_type_id;
    }

    /**
     * Get color field
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    /**
     * Get orderby field
     *
     * @return int
     */
    public function getOrderby()
    {
        return $this->orderby;
    }

    /**
     * Set orderby field
     *
     * @param int $value
     */
    public function setOrderby($value)
    {
        $this->orderby = $value;
    }

    //endregion
}
