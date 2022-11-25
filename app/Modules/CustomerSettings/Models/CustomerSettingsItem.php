<?php

namespace App\Modules\CustomerSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class CustomerSettingsItem extends LogModel
{
    use TableFixTrait;

    protected $table = 'customer_settings_items';
    protected $primaryKey = 'id';

    protected $fillable = [
        'customer_settings_id',
        'key',
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

    /**
     * One-to-one relation with CustomerSettingsOption
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function getOptions()
    {
        return $this->belongsTo(
            \App\Modules\CustomerSettings\Models\CustomerSettingsOption::class,
            'key',
            'key'
        );
    }

    // scopes

    // getters

    /**
     * Get customer_settings_id data
     *
     * @return int
     */
    public function getCustomerSettingsId()
    {
        return $this->customer_settings_id;
    }

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
     * Get value data
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
