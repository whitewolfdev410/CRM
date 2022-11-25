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
use App\Modules\Invoice\Models\InvoiceBatchItem;
use App\Modules\ExternalServices\Models\ExternalLetter;
use App\Modules\Type\Models\Type;

class InvoiceBatch extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_batch';
    protected $primaryKey = 'invoice_batch_id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'table_name',
        'table_id',
        'person_id',
        'status_type_id',
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
     * One-to-many relation with batch items
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(InvoiceBatchItem::class, 'invoice_batch_id');
    }

    /**
     * Letter that are mapped to batch
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|null
     */
    public function letter()
    {
        return $this->hasOne(ExternalLetter::class, 'id', 'table_id');
    }

    /**
     * Person associated with the invoice batch
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    /**
     * Type associated with the invoice batch
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status()
    {
        return $this->belongsTo(Type::class, 'status_type_id', 'type_id');
    }
}
