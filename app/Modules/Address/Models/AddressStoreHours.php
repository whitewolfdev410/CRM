<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class AddressStoreHours extends LogModel
{
    use TableFixTrait;

    protected $table = 'address_store_hours';
    protected $primaryKey = 'address_id';

    protected $fillable = [
        'address_id',
        'store_name',
        'store_phone_number',
        'is_mall',
        'mall_name',
        'mall_phone_number',
        'monday_open_at',
        'monday_close_at',
        'tuesday_open_at',
        'tuesday_close_at',
        'wednesday_open_at',
        'wednesday_close_at',
        'thursday_open_at',
        'thursday_close_at',
        'friday_open_at',
        'friday_close_at',
        'saturday_open_at',
        'saturday_close_at',
        'sunday_open_at',
        'sunday_close_at',
        'saturday_is_open',
        'sunday_is_open',
        'created_at',
        'updated_at'
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
}
