<?php

namespace App\Modules\Invoice\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class InvoiceRepeat extends LogModel
{
    use TableFixTrait;

    protected $table = 'invoice_repeat';
    protected $primaryKey = 'invoice_repeat_id';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'interval',
        'interval_keyword',
        'reminder',
        'next_date',
        'days_in_advance',
        'number_remaining',
        'invoice_template_id'
    ];
}
