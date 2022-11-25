<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

/**
 * @property int    person_data_id
 * @property int    person_id
 * @property string data_key
 * @property string data_value
 *
 * @method Collection|PersonData[]|PersonData|EloquentBuilder|Builder ofPerson(int $personId)
 */
class PersonData extends LogModel
{
    use TableFixTrait;

    public $timestamps = false;

    protected $table = 'person_data';
    protected $primaryKey = 'person_data_id';

    protected $fillable = [
        'person_id',
        'data_key',
        'data_value',
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
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    //endregion

    //region accessors

    //region data_key

    /**
     * Gets data_key data
     *
     * @return string
     */
    public function getDataKey()
    {
        return $this->data_key;
    }

    /**
     * Sets data_key data
     *
     * @param string $value
     *
     * @return $this
     */
    public function setDataKey($value)
    {
        $this->data_key = $value;

        return $this;
    }

    //endregion

    //region data_value

    /**
     * Gets data_value data
     *
     * @return string
     */
    public function getDataValue()
    {
        return $this->data_value;
    }

    /**
     * Sets data_value data
     *
     * @param string $value
     *
     * @return $this
     */
    public function setDataValue($value)
    {
        $this->data_value = $value;

        return $this;
    }

    //endregion

    //region data_value

    /**
     * Gets person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return (int)$this->person_id;
    }

    /**
     * Sets person_id data
     *
     * @param int $value
     *
     * @return $this
     */
    public function setPersonId($value)
    {
        $this->person_id = (int)$value;

        return $this;
    }

    //endregion

    //endregion

    //region scopes

    /**
     * Scope a query to only person.
     *
     * @param EloquentBuilder $query
     * @param int             $personId
     *
     * @return Collection|PersonData[]|PersonData|EloquentBuilder|Builder
     */
    public function scopeOfPerson($query, $personId)
    {
        return $query
            ->where('person_id', '=', $personId);
    }

    //endregion
}
