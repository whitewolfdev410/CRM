<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class WorkOrderExtension extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_extension';
    protected $primaryKey = 'work_order_extension_id';

    const CREATED_AT = 'created_date';

    const SENT_TYPE_NONE = 0;
    const SENT_TYPE_DATE = 1;
    const SENT_TYPE_WITH_REASON = 3;

    protected $fillable
        = [
            'person_id',
            'reason',
            'extended_date',
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

    // relationships

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(\App\Modules\Person\Models\Person::class, 'person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with WorkOrder
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workOrder()
    {
        return $this->belongsTo(__NAMESPACE__ . '\WorkOrder', 'work_order_id', 'work_order_id');
    }

    // scopes

    // getters

    /**
     * Get person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Get reason data
     *
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Get extended_date data
     *
     * @return \Carbon\Carbon
     */
    public function getExtendedDate()
    {
        return $this->extended_date;
    }

    /**
     * Get work_order_id data
     *
     * @return int
     */
    public function getWorkOrderId()
    {
        return $this->work_order_id;
    }

    public function sentTypeWithReason()
    {
        return $this->sent_type == static::SENT_TYPE_WITH_REASON;
    }
}
