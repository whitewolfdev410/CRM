<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderTemplate
 *
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderTemplate extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_template';
    protected $primaryKey = 'work_order_template_id';

    public $timestamps = false;

    protected $fillable = [
        'template_name',
        'work_order_number',
        'company_person_id',
        'description',
        'acknowledged_person_id',
        'completion_code',
        'estimated_time',
        'trade',
        'trade_type_id',
        'request',
        'not_to_exceed',
        'instructions',
        'requested_by',
        'priority',
        'crm_priority_type_id',
        'category',
        'type',
        'fin_loc',
        'fac_supv',
        'wo_status_type_id',
        'via_type_id',
        'pickup_id',
        'shop_address_id',
        'acknowledged',
        'invoice_status_type_id',
        'bill_status_type_id',
        'quote_status_type_id',
        'project_manager_person_id',
        'received_date_interval',
        'expected_completion_date_interval',
        'wo_type_id'
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
