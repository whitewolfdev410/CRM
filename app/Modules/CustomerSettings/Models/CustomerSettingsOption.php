<?php

namespace App\Modules\CustomerSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class CustomerSettingsOption extends LogModel
{
    use TableFixTrait;

    protected $table = 'customer_settings_options';
    protected $primaryKey = 'id';

    protected $fillable = [
        'key',
        'label',
        'type',
        'options',
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
     * Get key data
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Get label data
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Get completion_code_format data
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get options data as array
     *
     * @return array
     */
    public function getOptions()
    {
        return json_decode($this->options, true);
    }
}
