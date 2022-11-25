<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * @property int id
 * @property int person_id
 * @property int link_person_wo_id
 * @property int work_order_id
 * @property int question_type_id
 * @property int quantity
 * @property string description
 * @property string created_at
 * @property string updated_at
 */
class WorkOrderTruckStock extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_truck_stock';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'id',
        'person_id',
        'link_person_wo_id',
        'work_order_id',
        'question_type_id',
        'quantity',
        'description',
        'created_at',
        'updated_at'
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }
}
