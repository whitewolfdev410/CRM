<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class AddressInfo extends LogModel
{
    use TableFixTrait;

    protected $table = 'address_info';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'address_id',
        'address_name',
        'location',
        'json_object',
        'status',
        'count_requests'
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
