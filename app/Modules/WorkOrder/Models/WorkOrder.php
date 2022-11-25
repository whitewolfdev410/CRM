<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Address\Models\Address;
use App\Modules\Asset\Models\LinkAssetWo;
use App\Modules\ExternalServices\Models\ExternalWorkOrderFile;
use App\Modules\ExternalServices\Models\ExternalWorkOrderIvr;
use App\Modules\ExternalServices\Models\ExternalWorkOrderNote;
use App\Modules\History\Models\History;
use App\Modules\Kb\Models\Article;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use App\Modules\WorkOrder\Events\WorkOrderImported;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * @property int    acknowledged
 * @property int    acknowledged_person_id
 * @property Carbon actual_completion_date
 * @property string address_1
 * @property string address_name
 * @property string attention
 * @property string authorization_code
 * @property int    bill_status_type_id
 * @property int    billing_company_person_id
 * @property int    cancel_reason_type_id
 * @property string category
 * @property string city
 * @property string client_status
 * @property int    company_person_id
 * @property string company_person_id_value
 * @property int    completion_code
 * @property float  costs
 * @property int    creator_person_id
 * @property int    crm_priority_type_id
 * @property int    customer_setting_id
 * @property string description
 * @property int    dispatched_to_person_id
 * @property string email
 * @property Carbon estimated_time
 * @property Carbon expected_completion_date
 * @property Carbon extended_date
 * @property string extended_why
 * @property string fac_supv
 * @property int    fax_pages
 * @property string fax_recipient
 * @property string fax_sender
 * @property string fin_loc
 * @property string instructions
 * @property string internal_number
 * @property float  invoice_amount
 * @property int    invoice_id
 * @property string invoice_number
 * @property int    invoice_status_type_id
 * @property int    locked_id
 * @property int    not_to_exceed
 * @property int    parts_status_type_id
 * @property int    pickup_id
 * @property string phone
 * @property int    priority
 * @property int    project_manager_person_id
 * @property string purchase_order
 * @property int    quote_status_type_id
 * @property Carbon received_date
 * @property string request
 * @property string requested_by
 * @property int    requested_by_person_id
 * @property Carbon requested_completion_date
 * @property Carbon requested_date
 * @property Carbon required_date
 * @property Carbon scheduled_date
 * @property string shop
 * @property int    shop_address_id
 * @property string state
 * @property string store_hours
 * @property int    supplier_person_id
 * @property string tracking_number
 * @property string trade
 * @property int    trade_type_id
 * @property string type
 * @property int    via_type_id
 * @property int    wo_status_type_id
 * @property string work_performed
 * @property int    work_order_id
 * @property string work_order_number
 * @property string zip_code
 * @property string comment
 * @property string subject
 *
 * @method Builder|WorkOrder isAtShopAddress(int $addressId)
 */
class WorkOrder extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order';
    protected $primaryKey = 'work_order_id';

    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'modified_date';

    protected $fillable = [];

    protected static $custPoNumberProvider;

    /**
     * Fillable that will be used for creating new Work order
     *
     * @var array
     */
    protected $createFillable
        = [
            'acknowledged',
            'authorization_code',
            'bill_status_type_id',
            'billing_company_person_id',
            'category',
            'company_person_id',
            'completion_code',
            'creator_person_id', // auto filled
            'crm_priority_type_id',
            'customer_setting_id',
            'description',
            'equipment_needed',
            'equipment_needed_text',
            'estimated_time',
            'expected_completion_date',
            'fac_supv',
            'fin_loc',
            'instructions',
            'invoice_number',
            'invoice_status_type_id',
            'not_to_exceed',
            'owner_person_id',
            'parts_status_type_id',
            'phone',
            'pickup_id',// auto filled when creating new work order
            'priority',
            'project_manager_person_id',
            'quote_status_type_id',
            'received_date',
            'region_id',
            'request',
            'requested_by',
            'requested_date',
            'sales_person_id',
            'scheduled_date',
            'shop_address_id',
            'store_hours',
            'supplier_person_id',
            'tech_trade_type_id',
            'trade',
            'trade_type_id',
            'via_type_id',
            'wo_status_type_id',
            'wo_type_id',
            'work_order_number',
            'alert_notes',
            'subject'
        ];

    /**
     * Fillable that will be used for editing (full edit form)  Work order
     *
     * @var array
     */
    protected $editFillable
        = [
            'acknowledged',
            'actual_completion_date',
            'authorization_code',
            'billing_company_person_id',
            'category',
            'company_person_id',
            'completion_code',
            'crm_priority_type_id',
            'customer_setting_id',
            'description',
            'equipment_needed',
            'equipment_needed_text',
            'estimated_time',
            'fac_supv',
            'fin_loc',
            'instructions',
            'invoice_number',
            'invoice_status_type_id',
            'not_to_exceed',
            'owner_person_id',
            'parts_status_type_id',
            'phone',
            'priority',
            'project_manager_person_id',
            'quote_status_type_id',
            'received_date',
            'region_id',
            'request',
            'requested_by',
            'requested_date',
            'sales_person_id',
            'scheduled_date',
            'shop_address_id',
            'store_hours',
            'supplier_person_id',
            'tech_trade_type_id',
            'trade',
            'trade_type_id',
            'via_type_id',
            'wo_type_id',
            'work_order_number',
        ];

    /**
     * Fillable that will be used for editing (basic edit form)  Work order
     *
     * @var array
     */
    protected $basicEditFillable
        = [
            'description',
            'request',
            'instructions',
        ];

    /**
     * Fillable that will be used for editing (work performed edit form) Work
     * order
     *
     * @var array
     */
    protected $noteEditFillable
        = [
            'work_performed',
        ];

    private $customerConfig;
    private $custPoNumber;

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
        } elseif ($type == 'basicedit') {
            $this->fillable($this->basicEditFillable);
        } elseif ($type == 'noteedit') {
            $this->fillable($this->noteEditFillable);
        }
    }

    /**
     * Set fillable array to to default (empty array)
     */
    public function clearFillable()
    {
        $this->fillable = [];
    }

    //region relationships

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function companyPerson()
    {
        return $this->belongsTo(Person::class, 'company_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function acknowledgedPerson()
    {
        return $this->belongsTo(Person::class, 'acknowledged_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dispatchedToPerson()
    {
        return $this->belongsTo(Person::class, 'dispatched_to_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creatorPerson()
    {
        return $this->belongsTo(Person::class, 'creator_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function projectManager()
    {
        return $this->belongsTo(Person::class, 'project_manager_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function requestedByPerson()
    {
        return $this->belongsTo(Person::class, 'requested_by_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function billingCompany()
    {
        return $this->belongsTo(Person::class, 'billing_company_person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tradeType()
    {
        return $this->belongsTo(Type::class, 'trade_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function woStatusType()
    {
        return $this->belongsTo(Type::class, 'wo_status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cancelReasonType()
    {
        return $this->belongsTo(Type::class, 'cancel_reason_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function viaType()
    {
        return $this->belongsTo(Type::class, 'via_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function crmPriorityType()
    {
        return $this->belongsTo(Type::class, 'crm_priority_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoiceStatusType()
    {
        return $this->belongsTo(Type::class, 'invoice_status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function billStatusType()
    {
        return $this->belongsTo(Type::class, 'bill_status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function quoteStatusType()
    {
        return $this->belongsTo(Type::class, 'quote_status_type_id', 'type_id');
    }

    /**
     * Get shop address for work order
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shopAddress()
    {
        return $this->belongsTo(Address::class, 'shop_address_id', 'address_id');
    }

    /**
     * One-to-many relationship with LinkAssetWo
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function linkedWorkOrderAssets()
    {
        return $this->hasMany(LinkAssetWo::class, 'work_order_id', 'work_order_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ownerPerson()
    {
        return $this->belongsTo(Person::class, 'owner_person_id', 'person_id');
    }

    /**
     * Many to many relation with Articles
     */
    public function articles()
    {
        return $this->belongsToMany(Article::class, 'link_article_wo', 'work_order_id', 'article_id')->withPivot('creator_person_id')->withPivot('created_date');
    }

    // @todo
    /*locked_id
    pickup_id
    customer_setting_id*/

    /**
     * One-to-many relationship with extensions
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function extensions()
    {
        return $this->hasMany(WorkOrderExtension::class, 'work_order_id', 'work_order_id');
    }

    /**
     * Get workorder extensions for basic edit
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detailedExtensions()
    {
        return $this->hasMany(WorkOrderExtension::class, 'work_order_id', 'work_order_id')
            ->selectRaw('work_order_extension.*, person_name(person_id) AS person_name')
            ->orderBy('created_date');
    }

    /**
     * One-to-many relationship with LinkPersonWo
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function linkedPersons()
    {
        return $this->hasMany(LinkPersonWo::class, 'work_order_id', 'work_order_id');
    }

    /**
     * Get bill numbers assigned to work order
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function billNumbers()
    {
        return $this->linkedPersons()
            ->select('link_person_wo_id', 'work_order_id', 'bill_number');
    }

    /**
     * Get assigned person to work order together with recall status
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function detailedRecalledLinkedPersons()
    {
        // @todo DATE_FORMAT here - probably date should be returned as it is in DB
        return $this->linkedPersons()
            ->leftJoin('type', 'link_person_wo.status_type_id', '=', 'type.type_id')
            ->selectRaw("
                link_person_wo_id,
                link_person_wo.work_order_id,
                person_name(link_person_wo.person_id) AS person_name,
                DATE_FORMAT(link_person_wo.created_date, '%m/%d/%y %h:%i %p') as created_date,
                type.type_value AS status,
                recall_link_person_wo_id,
                (SELECT lpwo2.link_person_wo_id
                   FROM link_person_wo lpwo2
                   WHERE lpwo2.recall_link_person_wo_id = link_person_wo.link_person_wo_id
                   AND lpwo2.`type` = 'recall' LIMIT 1
                ) AS is_recalled
            ");
    }

    /**
     * This is relation for getting linked persons with completed
     * status.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function completedLinkedPersons()
    {
        return $this->linkedPersons()->where(
            'status_type_id',
            '=',
            getTypeIdByKey('wo_vendor_status.completed')
        )->orderBy('completed_date');
    }

    /**
     * @return mixed
     */
    public function assignedTo()
    {
        $linkCancelled = getTypeIdByKey('wo_vendor_status.canceled');
        return $this->linkedPersons()
            ->leftJoin('type', 'link_person_wo.status_type_id', '=', 'type.type_id')
            ->selectRaw("
                link_person_wo_id,
                link_person_wo.work_order_id,
                person_name(link_person_wo.person_id) AS person_name,
                DATE_FORMAT(link_person_wo.created_date, '%m/%d/%y %h:%i %p') as created_date,
                type.type_value AS status,
                recall_link_person_wo_id,
                (SELECT lpwo2.link_person_wo_id
                   FROM link_person_wo lpwo2
                   WHERE lpwo2.recall_link_person_wo_id = link_person_wo.link_person_wo_id
                   AND lpwo2.`type` = 'recall' LIMIT 1
                ) AS is_recalled
            ")
            ->whereRaw('link_person_wo.status_type_id <> '.$linkCancelled.' ');
    }
    /**
     * Get pickup date for Work Order (used because of missing where closure in
     * JoinClause)
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pickupDate()
    {
        return $this->hasOne(History::class, 'record_id', 'work_order_id')
            ->where('tablename', 'work_order')
            ->where('columnname', 'pickup_id')
            ->where(function ($q) {
                $q->where('history.value_from', '=', '0')
                    ->orWhere('history.value_from', '=', '');
            })
            ->where('history.value_to', '>', '0')
            ->select('history.record_id', 'history.date_created');
    }

    /**
     * Photos from external service
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function externalFiles()
    {
        return $this->hasMany(ExternalWorkOrderFile::class, 'work_order_id');
    }

    /**
     * Notes from external service
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function externalNotes()
    {
        return $this->hasMany(ExternalWorkOrderNote::class, 'work_order_id');
    }

    /**
     * IVR from external service
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function externalIvr()
    {
        return $this->hasMany(ExternalWorkOrderIvr::class, 'work_order_id');
    }

    //endregion

    /**
     * Get real person id (billing company person or company person
     * depending on billing company person id value)
     *
     * @return int
     */
    public function getRealCompanyPersonId()
    {
        if ($this->getBillingCompanyPersonId() > 0) {
            return $this->getBillingCompanyPersonId();
        }

        return $this->getCompanyPersonId();
    }

    //region scopes

    /**
     * Scope a query to only include work orders that are at to shop address.
     *
     * @param Builder $query
     * @param int     $addressId
     *
     * @return Builder|WorkOrder
     *
     * @throws InvalidArgumentException
     */
    public function scopeIsAtShopAddress($query, $addressId)
    {
        return $query->where('shop_address_id', '=', $addressId);
    }

    //endregion

    //region accessors

    /**
     * Get work_order_number data
     *
     * @return string
     */
    public function getWorkOrderNumber()
    {
        return $this->work_order_number;
    }

    /**
     * Get company_person_id data
     *
     * @return int
     */
    public function getCompanyPersonId()
    {
        return $this->company_person_id;
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

    /**
     * Get received_date data
     *
     * @return \Carbon\Carbon
     */
    public function getReceivedDate()
    {
        return $this->received_date;
    }

    /**
     * Get acknowledged_person_id data
     *
     * @return int
     */
    public function getAcknowledgedPersonId()
    {
        return $this->acknowledged_person_id;
    }

    /**
     * Get expected_completion_date data
     *
     * @return \Carbon\Carbon
     */
    public function getExpectedCompletionDate()
    {
        return $this->expected_completion_date;
    }

    /**
     * Get dispatched_to_person_id data
     *
     * @return int
     */
    public function getDispatchedToPersonId()
    {
        return $this->dispatched_to_person_id;
    }

    /**
     * Get actual_completion_date data
     *
     * @return \Carbon\Carbon
     */
    public function getActualCompletionDate()
    {
        return $this->actual_completion_date;
    }

    /**
     * Get completion_code data
     *
     * @return string
     */
    public function getCompletionCode()
    {
        return $this->completion_code;
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
     * Get tracking_number data
     *
     * @return int
     */
    public function getTrackingNumber()
    {
        return $this->tracking_number;
    }

    /**
     * Get attention data
     *
     * @return string
     */
    public function getAttention()
    {
        return $this->attention;
    }

    /**
     * Get trade data
     *
     * @return string
     */
    public function getTrade()
    {
        return $this->trade;
    }

    /**
     * Get trade_type_id data
     *
     * @return int
     */
    public function getTradeTypeId()
    {
        return $this->trade_type_id;
    }

    /**
     * Get request data
     *
     * @return string
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get not_to_exceed data
     *
     * @return string
     */
    public function getNotToExceed()
    {
        return $this->not_to_exceed;
    }

    /**
     * Get requested_completion_date data
     *
     * @return \Carbon\Carbon
     */
    public function getRequestedCompletionDate()
    {
        return $this->requested_completion_date;
    }

    /**
     * Get instructions data
     *
     * @return string
     */
    public function getInstructions()
    {
        return $this->instructions;
    }

    /**
     * Get fax_sender data
     *
     * @return string
     */
    public function getFaxSender()
    {
        return $this->fax_sender;
    }

    /**
     * Get fax_recipient data
     *
     * @return string
     */
    public function getFaxRecipient()
    {
        return $this->fax_recipient;
    }

    /**
     * Get fax_pages data
     *
     * @return int
     */
    public function getFaxPages()
    {
        return $this->fax_pages;
    }

    /**
     * Get requested_by data
     *
     * @return string
     */
    public function getRequestedBy()
    {
        return $this->requested_by;
    }

    /**
     * Get phone data
     *
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Get email data
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Get requested_date data
     *
     * @return \Carbon\Carbon
     */
    public function getRequestedDate()
    {
        return $this->requested_date;
    }

    /**
     * Get required_date data
     *
     * @return \Carbon\Carbon
     */
    public function getRequiredDate()
    {
        return $this->required_date;
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
     * Get crm_priority_type_id data
     *
     * @return int
     */
    public function getCrmPriorityTypeId()
    {
        return $this->crm_priority_type_id;
    }

    /**
     * Get category data
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Get type data
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get fin_loc data
     *
     * @return string
     */
    public function getFinLoc()
    {
        return $this->fin_loc;
    }

    /**
     * Get store_hours data
     *
     * @return string
     */
    public function getStoreHours()
    {
        return $this->store_hours;
    }

    /**
     * Get shop data
     *
     * @return string
     */
    public function getShop()
    {
        return $this->shop;
    }

    /**
     * Get fac_supv data
     *
     * @return string
     */
    public function getFacSupv()
    {
        return $this->fac_supv;
    }

    /**
     * Get wo_status_type_id data
     *
     * @return int
     */
    public function getWoStatusTypeId()
    {
        return $this->wo_status_type_id;
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
     * Get via_type_id data
     *
     * @return int
     */
    public function getViaTypeId()
    {
        return $this->via_type_id;
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
     * Get extended_why data
     *
     * @return string
     */
    public function getExtendedWhy()
    {
        return $this->extended_why;
    }

    /**
     * Get invoice_id data
     *
     * @return int
     */
    public function getInvoiceId()
    {
        return $this->invoice_id;
    }

    /**
     * Get invoice_amount data
     *
     * @return float
     */
    public function getInvoiceAmount()
    {
        return $this->invoice_amount;
    }

    /**
     * Get costs data
     *
     * @return float
     */
    public function getCosts()
    {
        return $this->costs;
    }

    /**
     * Get locked_id data
     *
     * @return int
     */
    public function getLockedId()
    {
        if ($this->getUpdatedAt() && Carbon::now()->subMinutes(5)->format('Y-m-d H:i:s') > $this->getUpdatedAt()) {
            return null;
        } else {
            return $this->locked_id;
        }
    }

    /**
     * Get pickup_id data
     *
     * @return int
     */
    public function getPickupId()
    {
        return $this->pickup_id;
    }

    /**
     * Get pickup_date data
     *
     * @return int
     */
    public function getPickupDate()
    {
        return $this->pickup_date;
    }
    
    /**
     * Get shop_address_id data
     *
     * @return int
     */
    public function getShopAddressId()
    {
        return $this->shop_address_id;
    }

    /**
     * Get acknowledged data
     *
     * @return int
     */
    public function getAcknowledged()
    {
        return $this->acknowledged;
    }

    /**
     * Get invoice_status_type_id data
     *
     * @return int
     */
    public function getInvoiceStatusTypeId()
    {
        return $this->invoice_status_type_id;
    }

    /**
     * Get bill_status_type_id data
     *
     * @return int
     */
    public function getBillStatusTypeId()
    {
        return $this->bill_status_type_id;
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
     * Get quote_status_type_id data
     *
     * @return int
     */
    public function getQuoteStatusTypeId()
    {
        return $this->quote_status_type_id;
    }

    /**
     * Get invoice_number data
     *
     * @return string
     */
    public function getInvoiceNumber()
    {
        return $this->invoice_number;
    }

    /**
     * Get project_manager_person_id data
     *
     * @return int
     */
    public function getProjectManagerPersonId()
    {
        return $this->project_manager_person_id;
    }

    /**
     * Get scheduled_date data
     *
     * @return \Carbon\Carbon
     */
    public function getScheduledDate()
    {
        return $this->scheduled_date;
    }

    /**
     * Get authorization_code data
     *
     * @return string
     */
    public function getAuthorizationCode()
    {
        return $this->authorization_code;
    }

    /**
     * Get requested_by_person_id data
     *
     * @return int
     */
    public function getRequestedByPersonId()
    {
        return $this->requested_by_person_id;
    }

    /**
     * Get billing_company_person_id data
     *
     * @return int
     */
    public function getBillingCompanyPersonId()
    {
        return $this->billing_company_person_id;
    }

    /**
     * Get customer_setting_id data
     *
     * @return int
     */
    public function getCustomerSettingId()
    {
        return $this->customer_setting_id;
    }

    /**
     * Get client_status data
     *
     * @return string
     */
    public function getClientStatus()
    {
        return $this->client_status;
    }

    /**
     * Get parts_status_type_id data
     *
     * @return int
     */
    public function getPartsStatusTypeId()
    {
        return $this->parts_status_type_id;
    }

    /**
     * Get supplier_person_id data
     *
     * @return int
     */
    public function getSupplierPersonId()
    {
        return $this->supplier_person_id;
    }

    /**
     * Get purchase_order data
     *
     * @return string
     */
    public function getPurchaseOrder()
    {
        return $this->purchase_order;
    }

    /**
     * Get work_performed data
     *
     * @return string
     */
    public function getWorkPerformed()
    {
        return $this->work_performed;
    }

    /**
     * Return vendors count
     *
     * @param  array $statusKeys
     *
     * @return int
     */
    public function getVendorsCount(array $statusKeys = [])
    {
        $sql = "SELECT count(l.link_person_wo_id) AS cnt
            FROM link_person_wo l
            WHERE l.work_order_id = {$this->work_order_id} ";

        if ($statusKeys) {
            $keys = "'" . implode("','", $statusKeys) . "'";

            $sql .= " AND l.status_type_id IN (
                SELECT t.type_id FROM `type` t
                WHERE t.type_key IN ({$keys}))";
        }

        $result = DB::select(DB::raw($sql));

        return (int)$result[0]->cnt;
    }

    /**
     * Get purchase order number or work order number
     *
     * @return string
     */
    public function getPoOrWoNumber()
    {
        $poNumber = trim($this->purchase_order);
        $woNumber = $this->work_order_number;

        return empty($poNumber) ? $woNumber : $poNumber;
    }

    /**
     * Fire WorkOrderImported event
     * @param  string $source
     * @param  array  $sourceData
     * @return void
     */
    public function fireImportedEvent($source, $sourceData = [])
    {
        if (isset(static::$dispatcher)) {
            static::$dispatcher->dispatch(new WorkOrderImported($this, $source, $sourceData));
        }
    }

    /**
     * Get customer PO number.
     * This is the work order reference/tracking number used in customer's system
     * @return string
     */
    public function getCustPoNumber()
    {
        if (!isset($this->custPoNumber)) {
            if ($fn = static::$custPoNumberProvider) {
                $this->custPoNumber = $fn($this);
            } else {
                $this->custPoNumber = $this->getWorkOrderNumber();
            }
        }

        return $this->custPoNumber;
    }

    /**
     * Set global customer PO number provider function
     * @param callable $fn
     * @return void
     */
    public static function setCustPoNumberProvider(callable $fn)
    {
        static::$custPoNumberProvider = $fn;
    }

    /**
     * Get CustomerConfig
     * @return CustomerConfig
     */
    public function getCustomerConfig()
    {
        return $this->customerConfig;
    }

    /**
     * Set CustomerConfig
     * @param CustomerConfig $customerConfig
     */
    public function setCustomerConfig($customerConfig)
    {
        $this->customerConfig = $customerConfig;
    }

    /**
     * Get customer name
     * @return string|null
     */
    public function getCustomerName()
    {
        if ($this->companyPerson) {
            return $this->companyPerson->custom_1;
        }
    }

    /**
     * Get the default linked person for the Target integration
     * @return LinkPersonWo
     */
    public function getTargetLinkedPerson()
    {
        return $this->linkedPersons()->orderByDesc('target_visit_id')->orderByDesc('link_person_wo_id')->first();
    }

    //endregion
}
