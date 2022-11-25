<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceImportException extends Model
{
    protected $table = 'invoice_import_exception';

    protected $guarded = [];
}
