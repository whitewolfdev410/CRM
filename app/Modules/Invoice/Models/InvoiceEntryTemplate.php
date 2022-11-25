<?php

namespace App\Modules\Invoice\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEntryTemplate extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_entry_template';
    protected $primaryKey = 'invoice_entry_template_id';

    public $timestamps = false;

    protected $fillable = [
        'entry_short',
        'entry_long',
        'qty',
        'price',
        'total',
        'unit',
        'entry_date_interval',
        'service_id',
        'service_id2',
        'item_id',
        'person_id',
        'invoice_template_id',
        'order_id',
        'calendar_event_id',
        'is_disabled',
        'func',
        'tax_rate',
        'tax_amount',
        'discount',
        'packaged',
        'creator_person_id',
        'register_id',
        'currency'
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
    
    /**
     * Invoice template entry is assigned to one invoice
     *
     * @return BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo(InvoiceTemplate::class, 'invoice_template_id');
    }
}
