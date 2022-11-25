<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * @property int person_note_id
 * @property int person_id
 * @property string note
 * @property int created_by
 * @property string created_at
 * @property string updated_at
 */
class PersonNote extends LogModel
{
    use TableFixTrait;

    protected $table = 'person_note';
    protected $primaryKey = 'person_note_id';

    protected $fillable = [
        'person_id',
        'note',
        'created_by'
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param  array  $attributes
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

    /**
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * @return string
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * @return int
     */
    public function getCreatedBy()
    {
        return $this->created_by;
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    //endregion
}
