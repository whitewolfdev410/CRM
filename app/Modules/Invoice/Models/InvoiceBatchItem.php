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
use Illuminate\Database\Query\Builder;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceBatch;

class InvoiceBatchItem extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_batch_item';
    protected $primaryKey = 'invoice_batch_item_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'invoice_batch_id',
        'invoice_id',
        'created_at',
        'updated_at'
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
     * Invoice may belong to an batch item
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|Invoice
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Batch may belong to an batch item
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|Invoice
     */
    public function batch()
    {
        return $this->belongsTo(InvoiceBatch::class, 'invoice_batch_id');
    }
}
