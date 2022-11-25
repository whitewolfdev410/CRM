<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderLiveAction
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderLiveActionToOrder extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_live_action_to_order';
    protected $primaryKey = 'work_order_live_action_to_order_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable
        = [
            'work_order_live_action_id',
            'link_person_wo_id',
            'truck_order_id',
            'address_id',
            'action_type',
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
