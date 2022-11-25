<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LinkPersonWo
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property float     bill_amount
 * @property Carbon    bill_date
 * @property string    bill_description
 * @property int       bill_final
 * @property string    bill_number
 * @property int       cancel_reason_type_id
 * @property Carbon    confirmed_date
 * @property int       creator_person_id
 * @property Carbon    disabled_date
 * @property int       disabling_person_id
 * @property int       estimated_time
 * @property int       is_disabled
 * @property int       is_hidden
 * @property int       last_past_due_notice_number
 * @property int       link_person_wo_id
 * @property int       person_id
 * @property int       person_permission
 * @property int       person_type
 * @property int       priority
 * @property string    qb_info
 * @property string    qb_ref
 * @property Carbon    qb_transfer_date
 * @property int       recall_link_person_wo_id
 * @property string    reference_number
 * @property int       send_past_due_notice
 * @property Carbon    sleep_due_date
 * @property string    sleep_reason
 * @property int       special_type
 * @property int       status
 * @property int       status_type_id
 * @property int       tech_status_type_id
 * @property string    tech_status_date
 * @property string    type
 * @property string    vendor_notes
 * @property int       work_order_id
 * @property WorkOrder workOrder
 * @property float qb_nte
 * @property string qb_ecd
 * @property string scheduled_date
 * @property string scheduled_date_simple
 * @property mixed completed_pictures_required
 * @property mixed completed_pictures_received
 * @property int primary_technician
 * @property int is_ghost
 *
 * @method Builder|EloquentBuilder|LinkPersonWo ofPerson(int $personId)
 * @method Builder|EloquentBuilder|LinkPersonWo ofWorkOrder(int $workOrderId)
 */
class LinkPersonWo extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_person_wo';
    protected $primaryKey = 'link_person_wo_id';

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'modified_date';

    protected $fillable = [];

    /**
     * Permission types
     */
    const PERM_NONE = 0;
    const PERM_VIEW = 1;
    const PERM_EDIT = 2;
    const PERM_LINK_OTHER = 4;

    // decimals(...,2) should be casted to double to be numbers and not strings
    protected $casts = [
        'qb_nte' => 'double',
    ];

    /**
     * Allowed types for type property
     *
     * @var array
     */
    public static $types = ['quote', 'recall', 'work'];

    /**
     * Allowed types for special_type property
     *
     * @var array
     */
    public static $specialTypes = ['none', '2hr_min'];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
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
    public function companyPerson()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
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

    /**
     * Many-to-one relationship with Person
     *
     * @return Builder|BelongsTo
     */
    public function creatorPerson()
    {
        return $this->belongsTo(Person::class, 'creator_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return Builder|BelongsTo
     */
    public function disablingPerson()
    {
        return $this->belongsTo(Person::class, 'disabling_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function cancelReasonType()
    {
        return $this->belongsTo(Type::class, 'cancel_reason_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function statusType()
    {
        return $this->belongsTo(Type::class, 'status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function techStatusType()
    {
        return $this->belongsTo(Type::class, 'tech_status_type_id', 'type_id');
    }

    // @todo recall_link_person_wo_id

    //endregion

    //region accessors

    /**
     * Return link ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->link_person_wo_id;
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
     * Get creator_person_id data
     *
     * @return int
     */
    public function getCreatorPersonId()
    {
        return $this->creator_person_id;
    }

    /**
     * Get bill_final data
     *
     * @return int
     */
    public function getBillFinal()
    {
        return $this->bill_final;
    }

    /**
     * Get bill_number data
     *
     * @return string
     */
    public function getBillNumber()
    {
        return $this->bill_number;
    }

    /**
     * Get bill_amount data
     *
     * @return float
     */
    public function getBillAmount()
    {
        return $this->bill_amount;
    }

    /**
     * Get bill_date data
     *
     * @return Carbon
     */
    public function getBillDate()
    {
        return $this->bill_date;
    }

    /**
     * Get bill_description data
     *
     * @return string
     */
    public function getBillDescription()
    {
        return $this->bill_description;
    }

    /**
     * Get vendor_notes data
     *
     * @return string
     */
    public function getVendorNotes()
    {
        return $this->vendor_notes;
    }

    /**
     * Get qb_ref data
     *
     * @return string
     */
    public function getQbRef()
    {
        return $this->qb_ref;
    }

    /**
     * Get qb_transfer_date data
     *
     * @return Carbon
     */
    public function getQbTransferDate()
    {
        return $this->qb_transfer_date;
    }

    /**
     * Get qb_info data
     *
     * @return string
     */
    public function getQbInfo()
    {
        return $this->qb_info;
    }

    /**
     * Get confirmed_date data
     *
     * @return Carbon
     */
    public function getConfirmedDate()
    {
        return $this->confirmed_date;
    }

    /**
     * Get status data
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get is_disabled data
     *
     * @return int
     */
    public function getIsDisabled()
    {
        return $this->is_disabled;
    }

    /**
     * Get disabling_person_id data
     *
     * @return int
     */
    public function getDisablingPersonId()
    {
        return $this->disabling_person_id;
    }

    /**
     * Get disabled_date data
     *
     * @return Carbon
     */
    public function getDisabledDate()
    {
        return $this->disabled_date;
    }

    /**
     * Get cancel_reason_type_id data
     *
     * @return int
     */
    public function getCancelReasonTypeId()
    {
        return $this->cancel_reason_type_id;
    }

    /**
     * Get status_type_id data
     *
     * @return int
     */
    public function getStatusTypeId()
    {
        return $this->status_type_id;
    }

    /**
     * Get type data
     *
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get recall_link_person_wo_id data
     *
     * @return int
     */
    public function getRecallLinkPersonWoId()
    {
        return $this->recall_link_person_wo_id;
    }

    /**
     * Get is_hidden data
     *
     * @return int
     */
    public function getIsHidden()
    {
        return $this->is_hidden;
    }

    /**
     * Get priority data
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Get special_type data
     *
     * @return int
     */
    public function getSpecialType()
    {
        return $this->special_type;
    }

    /**
     * Get estimated_time data
     *
     * @return int
     */
    public function getEstimatedTime()
    {
        return $this->estimated_time;
    }

    /**
     * Get send_past_due_notice data
     *
     * @return int
     */
    public function getSendPastDueNotice()
    {
        return $this->send_past_due_notice;
    }

    /**
     * Get last_past_due_notice_number data
     *
     * @return int
     */
    public function getLastPastDueNoticeNumber()
    {
        return $this->last_past_due_notice_number;
    }

    /**
     * Get person_type data
     *
     * @return int
     */
    public function getPersonType()
    {
        return $this->person_type;
    }

    /**
     * Get person_permission data
     *
     * @return int
     */
    public function getPersonPermission()
    {
        return $this->person_permission;
    }

    /**
     * Get sleep_due_date data
     *
     * @return Carbon
     */
    public function getSleepDueDate()
    {
        return $this->sleep_due_date;
    }

    /**
     * Get sleep_reason data
     *
     * @return string
     */
    public function getSleepReason()
    {
        return $this->sleep_reason;
    }

    /**
     * Get reference_number data
     *
     * @return string
     */
    public function getReferenceNumber()
    {
        return $this->reference_number;
    }

    /**
     * Return tech status type ID
     *
     * @return int
     */
    public function getTechStatusTypeId()
    {
        return $this->tech_status_type_id;
    }

    /**
     * Return tech status date
     *
     * @return string
     */
    public function getTechStatusDate()
    {
        return $this->tech_status_date;
    }
    
    //endregion

    //region scopes

    /**
     * @param EloquentBuilder $query
     * @param int             $personId
     *
     * @return Builder|EloquentBuilder|LinkPersonWo $this
     */
    public function scopeOfPerson($query, $personId)
    {
        return $query->where($this->table . '.person_id', '=', $personId);
    }

    /**
     * @param EloquentBuilder $query
     * @param int             $workOrderId
     *
     * @return Builder|EloquentBuilder|LinkPersonWo $this
     */
    public function scopeOfWorkOrder($query, $workOrderId)
    {
        return $query->where($this->table . '.work_order_id', '=', $workOrderId);
    }

    //endregion
    public function getRoutePlanner()
    {
        $routePlanner = new \stdClass();
        $routePlanner->driverKey = 'test3';
        $routePlanner->routeId = 'Resource-284-23';
        $routePlanner->stopId = 'SHOE0419';
        
        return $routePlanner;
    }
}
