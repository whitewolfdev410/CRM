<?php

namespace App\Modules\Invoice\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Item\Models\Item;
use App\Modules\Service\Models\Service;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;

/**
 * @property int    calendar_event_id
 * @property int    creator_person_id
 * @property string currency
 * @property float  discount
 * @property Carbon entry_date
 * @property string entry_long
 * @property string entry_short
 * @property string func
 * @property int    invoice_id
 * @property int    is_disabled
 * @property int    item_id
 * @property int    order_id
 * @property int    packaged
 * @property int    person_id
 * @property float  price
 * @property int    qty
 * @property int    register_id
 * @property int    service_id
 * @property int    service_id2
 * @property int    sort_order
 * @property int    table_id
 * @property string table_name
 * @property float  tax_amount
 * @property float  tax_rate
 * @property float  total
 * @property string unit
 *
 * @method Builder|EloquentBuilder|InvoiceEntry longEntryContains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|InvoiceEntry longEntryStartsWith(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|InvoiceEntry shortEntryContains(string $text, bool $or = false)
 * @method Builder|EloquentBuilder|InvoiceEntry shortEntryStartsWith(string $text, bool $or = false)
 */
class InvoiceEntry extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_entry';
    protected $primaryKey = 'invoice_entry_id';

    const CREATED_AT = 'created_date';

    protected $fillable = [
        'entry_short',
        'entry_long',
        'qty',
        'price',
        'total',
        'unit',
        'entry_date',
        'service_id',
        'item_id',
        'invoice_id',
        'person_id',
        'func',

        'created_by',
    ];

    // decimals(...,2) should be casted to double to be numbers and not strings
    protected $casts
        = [
            'price'    => 'double',
            'total'    => 'double',
            'discount' => 'double',
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

    //region relationships

    /**
     * Invoice entry is assigned to one invoice
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Service associated with the invoice entry
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Service associated with the invoice entry
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unitRel()
    {
        return $this->belongsTo(Type::class, 'unit', 'type_id');
    }

    //endregion

    //region scopes

    /**
     * Scope a query to only invoice entry whose field contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $field
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeFieldContains($query, $field, $text, $or = false)
    {
        return ($or ?
            $query->orWhere("invoice_entry.$field", 'LIKE', "%$text%") :
            $query->where("invoice_entry.$field", 'LIKE', "%$text%"));
    }

    /**
     * Scope a query to only invoice entry whose field starts with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $field
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeFieldStartsWith($query, $field, $text, $or = false)
    {
        return ($or ?
            $query->orWhere("invoice_entry.$field", 'LIKE', "$text%") :
            $query->where("invoice_entry.$field", 'LIKE', "$text%"));
    }

    /**
     * Scope a query to only invoice entry whose long entry contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeLongEntryContains($query, $text, $or = false)
    {
        return $this->scopeFieldContains($query, 'entry_long', $text, $or);
    }

    /**
     * Scope a query to only invoice entry whose long entry starts with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeLongEntryStartsWith($query, $text, $or = false)
    {
        return $this->scopeFieldStartsWith($query, 'entry_long', $text, $or);
    }

    /**
     * Scope a query to only invoice entry whose short entry contains the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeShortEntryContains($query, $text, $or = false)
    {
        return $this->scopeFieldContains($query, 'entry_short', $text, $or);
    }

    /**
     * Scope a query to only invoice entry whose short entry starts with the given text.
     *
     * @param Builder|EloquentBuilder $query
     * @param string                  $text
     * @param bool                    $or
     *
     * @return InvoiceEntry|EloquentBuilder|Builder
     */
    public function scopeShortEntryStartsWith($query, $text, $or = false)
    {
        return $this->scopeFieldStartsWith($query, 'entry_short', $text, $or);
    }

    //endregion

    //region accessors

    /**
     * Get entry_short data
     *
     * @return string
     */
    public function getEntryShort()
    {
        return $this->entry_short;
    }

    /**
     * Set entry_short data
     *
     * @param string $value
     */
    public function setEntryShort($value)
    {
        $this->entry_short = $value;
    }

    /**
     * Get entry_long data
     *
     * @return string
     */
    public function getEntryLong()
    {
        return $this->entry_long;
    }

    /**
     * Set entry_long data
     *
     * @param string $value
     */
    public function setEntryLong($value)
    {
        $this->entry_long = $value;
    }

    /**
     * Set entry_short and entry_long data
     *
     * @param string $value
     */
    public function setEntries($value)
    {
        $this->entry_short = $this->entry_long = $value;
    }

    /**
     * Get qty data
     *
     * @return float
     */
    public function getQty()
    {
        return $this->qty;
    }

    /**
     * Set qty data
     *
     * @param float $value
     */
    public function setQty($value)
    {
        $this->qty = $value;
    }

    /**
     * Get price data
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Get total data
     *
     * @return float
     */
    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Set price and total data
     *
     * @param float $value
     */
    public function setTotalPrice($value)
    {
        $this->price = $this->total = $value;
    }

    /**
     * Get unit data
     *
     * @return string
     */
    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * Get entry_date data
     *
     * @return Carbon
     */
    public function getEntryDate()
    {
        return $this->entry_date;
    }


    /**
     * Set entry_date data
     *
     * @param $value
     *
     * @return void
     */
    public function setEntryDate($value)
    {
        $this->entry_date = $value;
    }
    
    /**
     * Get service_id data
     *
     * @return int
     */
    public function getServiceId()
    {
        return $this->service_id;
    }

    /**
     * Set service_id data
     *
     * @param int $value
     */
    public function setServiceId($value)
    {
        $this->service_id = $value;
    }

    /**
     * Get service_id2 data
     *
     * @return int
     */
    public function getServiceId2()
    {
        return $this->service_id2;
    }

    /**
     * Get item_id data
     *
     * @return int
     */
    public function getItemId()
    {
        return $this->item_id;
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
     * Get invoice_id data
     *
     * @return int
     */
    public function getInvoiceId()
    {
        return $this->invoice_id;
    }

    /**
     * Set invoice_id data
     *
     * @param int $value
     */
    public function setInvoiceId($value)
    {
        $this->invoice_id = $value;
    }

    /**
     * Get order_id data
     *
     * @return int
     */
    public function getOrderId()
    {
        return $this->order_id;
    }

    /**
     * Get calendar_event_id data
     *
     * @return int
     */
    public function getCalendarEventId()
    {
        return $this->calendar_event_id;
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
     * Get func data
     *
     * @return string
     */
    public function getFunc()
    {
        return $this->func;
    }

    /**
     * Get tax_rate data
     *
     * @return float
     */
    public function getTaxRate()
    {
        return $this->tax_rate;
    }

    /**
     * Get tax_amount data
     *
     * @return float
     */
    public function getTaxAmount()
    {
        return $this->tax_amount;
    }

    /**
     * Set tax_amount and tax_rate data
     *
     * @param float $taxAmount
     * @param float $taxRate
     */
    public function setTax($taxAmount, $taxRate)
    {
        $this->tax_amount = $taxAmount;
        $this->tax_rate = $taxRate;
    }

    /**
     * Get discount data
     *
     * @return float
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * Get packaged data
     *
     * @return int
     */
    public function getPackaged()
    {
        return $this->packaged;
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
     * Get register_id data
     *
     * @return int
     */
    public function getRegisterId()
    {
        return $this->register_id;
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
     * Get sort_order data
     *
     * @return int
     */
    public function getSortOrder()
    {
        return $this->sort_order;
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
     * Recalculate price based on total and quantity
     * This solves rounding issue when amount was incorrectly calculated by JS during invoice creation
     * @return void
     */
    public function fixPrice()
    {
        if ($this->qty == 0) {
            // skip if 0 qty
            return;
        }

        // recalculate total from price and qty
        $calcTotal = round($this->price * $this->qty, 2);

        // if it's diffferent than stored total - update price and total
        if (\bccomp($calcTotal, $this->total, 2) != 0) {
            // recalculate price to match the total
            $this->price = round($this->total / $this->qty, 2);
            $this->total = round($this->price * $this->qty, 2);
        }
    }

    //endregion
}
