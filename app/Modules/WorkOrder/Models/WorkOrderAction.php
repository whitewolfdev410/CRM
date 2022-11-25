<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderAction
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderAction extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_action';
    protected $primaryKey = 'work_order_action_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable
        = [
            'work_order_id',
            'truck_order_id',
            'vehicle_name',
            'start_location',
            'stop_location',
            'travel_time_seconds',
            'time_there_seconds',
            'idle_time_seconds',
            'distance',
            'action_type',
            'odometer_start',
            'odometer_end',
            'start_at',
            'departure_at',
            'arrival_at'
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
