<?php

namespace App\Modules\Invoice\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceTemplate extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_template';
    protected $primaryKey = 'invoice_template_id';

    public $timestamps = false;

    protected $fillable = [
        'template_name',
        'person_id',
        'date_invoice_interval',
        'date_due_interval',
        'statement_id',
        'paid',
        'creator_person_id',
        'work_order_id',
        'currency'
    ];

    /**
     * Invoice template has many invoice entries
     *
     * @return HasMany
     */
    public function entries()
    {
        return $this->hasMany(InvoiceEntryTemplate::class, 'invoice_template_id', 'invoice_template_id');
    }
}
