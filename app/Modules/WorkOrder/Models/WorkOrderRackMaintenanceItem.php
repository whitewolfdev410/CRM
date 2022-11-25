<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * @property int    id
 * @property int    work_order_id
 * @property int    link_person_wo_id
 * @property string name
 * @property string start_at
 * @property string stop_at
 * @property int    notification_sent
 * @property string created_at
 * @property string updated_at
 */
class WorkOrderRackMaintenanceItem extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_rack_maintenance_items';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id',
        'work_order_id',
        'link_person_wo_id',
        'name',
        'start_at',
        'stop_at',
        'notification_sent',
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

    //endregion
}
