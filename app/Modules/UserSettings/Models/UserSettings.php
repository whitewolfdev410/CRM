<?php

namespace App\Modules\UserSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class UserSettings extends LogModel
{
    use TableFixTrait;

    protected $table = 'user_settings';
    protected $primaryKey = 'user_settings_id';

    protected $fillable = [
        'person_id',
        'type_id',
        'field_name',
        'value'
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

    // relationships

    // scopes

    // getters

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
     * Get field_name data
     *
     * @return string
     */
    public function getFieldName()
    {
        return $this->field_name;
    }

    /**
     * Get value data
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
