<?php

namespace App\Modules\Invoice\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Payment\Models\PaymentInvoice;
use App\Modules\Person\Models\Person;
use App\Modules\Queue\Models\QueuedJob;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;

/**
 * @property string                    billing_address_city
 * @property string                    billing_address_country
 * @property string                    billing_address_line1
 * @property string                    billing_address_line2
 * @property string                    billing_address_state
 * @property string                    billing_address_zip_code
 * @property string                    billing_person_name
 * @property int                       creator_person_id
 * @property string                    currency
 * @property string                    customer_request_description
 * @property Carbon                    date_due
 * @property Carbon                    date_invoice
 * @property int                       invoice_id
 * @property string                    invoice_number
 * @property string                    job_description
 * @property bool|int                  paid
 * @property int                       person_id
 * @property mixed                     qb_itemsalestax_listid
 * @property int                       ship_address_id
 * @property string                    shipping_address_city
 * @property string                    shipping_address_country
 * @property string                    shipping_address_line1
 * @property string                    shipping_address_line2
 * @property string                    shipping_address_state
 * @property string                    shipping_address_zip_code
 * @property string                    shipping_person_name
 * @property int                       statement_id
 * @property int                       table_id
 * @property string                    table_name
 * @property int                       work_order_id
 * @property string                    status_type_id
 * @property string                    customer_id
 *
 * @property InvoiceEntry[]|Collection entries
 * @property mixed                     paymentInvoices
 * @property float                     sum_tax_amount
 * @property float                     sum_total
 * @property WorkOrder                 workOrder
 *
 * @method Builder|EloquentBuilder|Invoice attemptSentAt(Carbon | string $date)
 * @method static Builder|EloquentBuilder|Invoice inIds(int [] $ids)
 */
class Invoice extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice';
    protected $primaryKey = 'invoice_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $fillable = [
        'invoice_number',
        'person_id',
        'date_due',
        'date_invoice',
        'customer_request_description',
        'customer_id',
        'bill_spec_name',
        'job_description',
        'status_type_id',
        'work_order_id',
        'qb_payment_status',
        'qb_payment_info',
        'qb_payment_date',
        'qb_payment_via',
        'qb_payment_amount',
        'qb_payment_discount_amount',
        'qb_tax_percent',
        'qb_tax_amount'
    ];

    protected $observables = ['imported'];

    private $amount_paid = -1;
    private $total = -1;
    private $total_tax = -1;
    private $total_with_tax = -1;

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
     * Invoice has many invoice entries
     *
     * @return HasMany
     */
    public function entries()
    {
        return $this->hasMany(InvoiceEntry::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Invoice has many queued jobs
     *
     * @return HasMany
     */
    public function queuedJobs()
    {
        return $this
            ->hasMany(QueuedJob::class, 'record_id', 'invoice_id')
            ->isSendingInvoice();
    }

    /**
     * One-to-Many relation with PaymentInvoice
     *
     * @return HasMany|EloquentBuilder|PaymentInvoice
     */
    public function paymentInvoices()
    {
        return $this->hasMany(PaymentInvoice::class, 'invoice_id', 'invoice_id');
    }

    /**
     * Person associated with the invoice
     *
     * @return BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Creator person associated with the invoice
     *
     * @return BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(Person::class, 'creator_person_id');
    }

    /**
     * Work order associated with the invoice
     *
     * @return BelongsTo
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    //endregion

    //region scopes

    public function scopeAttemptSentAt($query, $date)
    {
        /** @var EloquentBuilder $query */
        return $query->whereHas('queuedJobs', function ($queuedJobs) use ($date) {
            /** @var QueuedJob $queuedJobs */
            return $queuedJobs->completedAt($date);
        });
    }

    public function scopeInIds($query, $ids)
    {
        /** @var Builder|EloquentBuilder $query */
        return $query->whereIn('invoice_id', $ids);
    }

    //endregion

    //region accessors

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
     * Get person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Set person_id data
     *
     * @param int $value
     */
    public function setPersonId($value)
    {
        $this->person_id = $value;
    }

    /**
     * Get date_invoice data
     *
     * @return Carbon
     */
    public function getDateInvoice()
    {
        return $this->date_invoice;
    }

    /**
     * Set date_invoice data
     *
     * @param Carbon|string $value
     */
    public function setDateInvoice($value)
    {
        $this->date_invoice = $value;
    }

    /**
     * Get date_due data
     *
     * @return Carbon
     */
    public function getDateDue()
    {
        return $this->date_due;
    }

    /**
     * Set date_due data
     *
     * @param Carbon|string $value
     */
    public function setDateDue($value)
    {
        $this->date_due = $value;
    }

    /**
     * Get statement_id data
     *
     * @return int
     */
    public function getStatementId()
    {
        return $this->statement_id;
    }

    /**
     * Get paid data
     *
     * @return bool|int
     */
    public function getPaid()
    {
        return $this->paid;
    }

    /**
     * Set paid data
     *
     * @param bool|int $value
     */
    public function setPaid($value)
    {
        $this->paid = $value;
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
     * Set creator_person_id data
     *
     * @param int $value
     */
    public function setCreatorPersonId($value)
    {
        $this->creator_person_id = $value;
    }

    /**
     * Gets workOrder data
     *
     * @return WorkOrder
     */
    public function getWorkOrder()
    {
        return $this->workOrder;
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
     * Set work_order_id data
     *
     * @param int $value
     */
    public function setWorkOrderId($value)
    {
        $this->work_order_id = $value;
    }

    /**
     * Get table_name data
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * Get table_id data
     *
     * @return int
     */
    public function getTableId()
    {
        return $this->table_id;
    }

    /**
     * Set table_name and table_id data
     *
     * @param string $tableName
     * @param int    $tableId
     */
    public function setTableLink($tableName, $tableId)
    {
        $this->table_name = $tableName;
        $this->table_id = $tableId;
    }

    /**
     * Get customer_request_description data
     *
     * @return string
     */
    public function getCustomerRequestDescription()
    {
        return $this->customer_request_description;
    }

    /**
     * Get job_description data
     *
     * @return string
     */
    public function getJobDescription()
    {
        return $this->job_description;
    }

    /**
     * Get ship_address_id data
     *
     * @return int
     */
    public function getShipAddressId()
    {
        return $this->ship_address_id;
    }

    /**
     * Get currency data
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Get billing_person_name data
     *
     * @return string
     */
    public function getBillingPersonName()
    {
        return $this->billing_person_name;
    }

    /**
     * Get billing_address_line1 data
     *
     * @return string
     */
    public function getBillingAddressLine1()
    {
        return $this->billing_address_line1;
    }

    /**
     * Get billing_address_line2 data
     *
     * @return string
     */
    public function getBillingAddressLine2()
    {
        return $this->billing_address_line2;
    }

    /**
     * Get billing_address_city data
     *
     * @return string
     */
    public function getBillingAddressCity()
    {
        return $this->billing_address_city;
    }

    /**
     * Get billing_address_state data
     *
     * @return string
     */
    public function getBillingAddressState()
    {
        return $this->billing_address_state;
    }

    /**
     * Get billing_address_zip_code data
     *
     * @return string
     */
    public function getBillingAddressZipCode()
    {
        return $this->billing_address_zip_code;
    }

    /**
     * Get billing_address_country data
     *
     * @return string
     */
    public function getBillingAddressCountry()
    {
        return $this->billing_address_country;
    }

    /**
     * Get shipping_person_name data
     *
     * @return string
     */
    public function getShippingPersonName()
    {
        return $this->shipping_person_name;
    }

    /**
     * Get shipping_address_line1 data
     *
     * @return string
     */
    public function getShippingAddressLine1()
    {
        return $this->shipping_address_line1;
    }

    /**
     * Get shipping_address_line2 data
     *
     * @return string
     */
    public function getShippingAddressLine2()
    {
        return $this->shipping_address_line2;
    }

    /**
     * Get shipping_address_city data
     *
     * @return string
     */
    public function getShippingAddressCity()
    {
        return $this->shipping_address_city;
    }

    /**
     * Get shipping_address_state data
     *
     * @return string
     */
    public function getShippingAddressState()
    {
        return $this->shipping_address_state;
    }

    /**
     * Get shipping_address_zip_code data
     *
     * @return string
     */
    public function getShippingAddressZipCode()
    {
        return $this->shipping_address_zip_code;
    }

    /**
     * Get shipping_address_country data
     *
     * @return string
     */
    public function getShippingAddressCountry()
    {
        return $this->shipping_address_country;
    }

    /**
     * Get status_type_id data
     *
     * @return int
     */
    public function getStatusTypeId()
    {
        return (int)$this->status_type_id;
    }

    /**
     * Set qb_itemsalestax_listid data
     *
     * @param mixed $value
     */
    public function setQBItemSalesTaxListId($value)
    {
        $this->qb_itemsalestax_listid = $value;
    }

    public function getAmountDue()
    {
        return $this->getTotal() - $this->getAmountPaid();
    }

    public function getAmountPaid()
    {
        if ($this->amount_paid === -1) {
            $this->amount_paid = $this->paymentInvoices->sum('paymentsize');
        }

        return $this->amount_paid;
    }

    /**
     * Get total as sum of invoice_entry total
     *
     * @return int
     */
    public function getTotal()
    {
        if ($this->total === -1) {
            $this->total = $this->entries->sum('total');
        }

        return $this->total;
    }

    /**
     * Get total as sum of invoice_entry total and tax amount
     *
     * @return int
     */
    public function getTotalWithTax()
    {
        if ($this->total_with_tax === -1) {
            $this->total_with_tax = $this->entries->sum('total') + $this->entries->sum('tax_amount');
        }

        return $this->total_with_tax;
    }

    /**
     * Get total as sum of invoice_entry tax amount
     *
     * @return int
     */
    public function getTotalTax()
    {
        if ($this->total_tax === -1) {
            $this->total_tax = $this->entries->sum('tax_amount');
        }

        return $this->total_tax;
    }
    //endregion

    /**
     * Fire imported model event
     * @return void
     */
    public function fireImportedEvent()
    {
        $this->fireModelEvent('imported');
    }

    /**
     * Register a imported model event with the dispatcher
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function onImported($callback, $priority = 0)
    {
        static::registerModelEvent('imported', $callback, $priority);
    }
}
