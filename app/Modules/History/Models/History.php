<?php

namespace App\Modules\History\Models;

use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string action_type
 * @property string columnname
 * @property int    person_id
 * @property int    record_id
 * @property int    related_record_id
 * @property string related_tablename
 * @property string tablename
 * @property mixed  value_from
 * @property mixed  value_to
 */
class History extends Model
{
    use TableFixTrait;

    protected $table = 'history';
    protected $primaryKey = 'history_id';
    protected $hidden = ['updated_at'];

    const CREATED_AT = 'date_created';
    const UPDATED_AT = ''; // no updated field in this table

    public $timestamps = false;

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region accessors

    /**
     * Get person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Get tablename data
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->tablename;
    }

    /**
     * Get record_id data
     *
     * @return int
     */
    public function getRecordId()
    {
        return $this->record_id;
    }

    /**
     * Get related_tablename data
     *
     * @return string
     */
    public function getRelatedTableName()
    {
        return $this->related_tablename;
    }

    /**
     * Get related_record_id data
     *
     * @return int
     */
    public function getRelatedRecordId()
    {
        return $this->related_record_id;
    }

    /**
     * Get columnname data
     *
     * @return string
     */
    public function getColumnName()
    {
        return $this->columnname;
    }

    /**
     * Get value_from data
     *
     * @return string
     */
    public function getValueFrom()
    {
        return $this->value_from;
    }

    /**
     * Get value_to data
     *
     * @return string
     */
    public function getValueTo()
    {
        return $this->value_to;
    }

    /**
     * Get action_type data
     *
     * @return string
     */
    public function getActionType()
    {
        return $this->action_type;
    }

    /**
     * Get changes data
     *
     * @return string
     */
    public function getChanges()
    {
        if (isset($this->changes)) {
            return $this->changes;
        }

        return '';
    }

    //endregion

    //region relationships

    /**
     * Many-to-one relation with Person
     *
     * @return BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    //endregion
}
