<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class ServiceRequest extends LogModel
{
    use TableFixTrait;

    protected $table = 'service_request';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'modified_date';

    protected $fillable = [
        'status',
        'requested_by',
        'work_order_id',
    ];

    /**
     * Fillable that will be used for creating new Work order
     *
     * @var array
     */
    protected $createFillable
        = [
            'company_person_id',
            'crm_priority_type_id',
            'billing_company_person_id',
            'shop_address_id',
            'request_date',
            'trade_type_id',
            'category',
            'expected_completion_date',
            'description',
            'not_to_exceed',
            'requested_by',
        ];

    /**
     * Fillable that will be used for editing (full edit form)  Work order
     *
     * @var array
     */
    protected $editFillable
        = [
            'crm_priority_type_id',
            'shop_address_id',
            'request_date',
            'trade_type_id',
            'category',
            'expected_completion_date',
            'description',
            'not_to_exceed',
            'status',
            'requested_by',
            'work_order_id',
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
     * Set fillable array based on given $type
     *
     * @param string $type
     */
    public function setFillableType($type)
    {
        if ($type == 'create') {
            $this->fillable($this->createFillable);
        } elseif ($type == 'edit') {
            $this->fillable($this->editFillable);
        }
    }

    /**
     * Set fillable array to to default (empty array)
     */
    public function clearFillable()
    {
        $this->fillable = [];
    }
}
