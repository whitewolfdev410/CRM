<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\WorkOrder\Models\WorkOrderLiveActionToOrder;
use Illuminate\Support\Facades\DB;

/**
 * Class WorkOrderLiveAction
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderLiveAction extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_live_action';
    protected $primaryKey = 'work_order_live_action_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable
        = [
            'address_id',
            'vehicle_number',
            'vehicle_name',
            'idle_time',
            'stop_time',
            'moving_time',
            'towing_time',
            'control',
            'odometer_from',
            'odometer_to',
            'truck_order_id',
            'link_person_wo_id',
            'address_line_1',
            'address_line_2',
            'locality',
            'administrative_area',
            'postal_code',
            'country',
            'delta_distance',
            'delta_time',
            'vehicle_status',
            'latitude',
            'longitude',
            'action_type',
            'speed',
            'action_date_from',
            'action_date_to',
            'created_at',
            'updated_at',
            'last_sms_date',
            'last_email_date',
            'last_dispatcher_email_date',
            'first_reached_entry',
            'number_of_updates'
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

    /**
     * One-to-many relation with LinkPersonCompany
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(WorkOrderLiveActionToOrder::class, 'work_order_live_action_id', 'work_order_live_action_id');
    }

    /**
     * Get prev action for current action
     *
     * @return WorkOrderLiveAction|null
     */
    public function getPrevAction()
    {
        return WorkOrderLiveAction::where('vehicle_number', '=', $this->vehicle_number)
            ->where('work_order_live_action_id', '<', $this->work_order_live_action_id)
            ->orderByDesc('work_order_live_action_id')
            ->first();
    }
}
