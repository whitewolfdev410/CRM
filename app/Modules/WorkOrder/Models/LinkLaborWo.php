<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LinkLaborWo
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property int       link_labor_wo_id
 * @property int       person_id
 * @property int       work_order_id
 * @property WorkOrder workOrder
 * @property string inventory_id
 * @property string name
 * @property string description
 * @property int    quantity
 * @property int    quantity_from_sl
 * @property float  unit_price
 * @property int    reason_type_id
 * @property string comment
 * @property int    accepted_quantity
 * @property int    accepted_person_id
 * @property string accepted_at
 * @property string created_at
 * @property string updated_at
 * @property mixed  seq_number
 * @property int    is_deleted
 *
 */
class LinkLaborWo extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_labor_wo';
    protected $primaryKey = 'link_labor_wo_id';
    protected $connection = 'mysql-utf8';

    protected $fillable = [
        'person_id',
        'work_order_id',
        'inventory_id',
        'name',
        'description',
        'quantity',
        'quantity_from_sl',
        'unit_price',
        'is_deleted',
        'reason_type_id',
        'comment',
        'seq_number',
        'accepted_quantity',
        'accepted_person_id',
        'accepted_at',
        'created_at',
        'is_deleted'
    ];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);

        parent::__construct($attributes);
    }

    //region relationships

    /**
     * Many-to-one relationship with WorkOrder
     *
     * @return Builder|BelongsTo
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id', 'work_order_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return Builder|BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    //endregion

    //region accessors

    /**
     * Return link ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->link_labor_wo_id;
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

    /**
     * Return Work Order
     *
     * @return WorkOrder
     */
    public function getWorkOrder()
    {
        return $this->workOrder;
    }

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
     * Get inventory_id data
     *
     * @return string
     */
    public function getInventoryId()
    {
        return $this->inventory_id;
    }

    /**
     * Get name data
     *
     * @param  bool  $limit
     *
     * @return string
     */
    public function getName($limit = false)
    {
        if ($limit) {
            return substr($this->name, 0, 30);
        } else {
            return $this->name;
        }
    }

    /**
     * Get description data
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function getSeqNumber()
    {
        return $this->seq_number;
    }
    
    /**
     * Get quantity data
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Get unit_price
     *
     * @return float
     */
    public function getUnitPrice()
    {
        return $this->unit_price;
    }

    /**
     * Get quantity_from_sl data
     *
     * @return int
     */
    public function getQuantityFromSL()
    {
        return $this->quantity_from_sl;
    }


    /**
     * @return int
     */
    public function getAcceptedQuantity()
    {
        return $this->accepted_quantity;
    }

    /**
     * @return int
     */
    public function getAcceptedPersonId()
    {
        return $this->accepted_person_id;
    }

    /**
     * @return string
     */
    public function getAcceptedAt()
    {
        return $this->accepted_at;
    }

    //endregion
}
