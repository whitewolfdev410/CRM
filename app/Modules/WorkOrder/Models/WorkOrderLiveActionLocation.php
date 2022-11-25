<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderLiveActionLocation
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderLiveActionLocation extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_live_action_location';
    protected $primaryKey = 'work_order_live_action_location_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable
        = [
            'address_id',
            'vehicle_number',
            'vehicle_name',
            'odometer',
            'address_line_1',
            'address_line_2',
            'locality',
            'administrative_area',
            'postal_code',
            'country',
            'vehicle_status',
            'latitude',
            'longitude',
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
