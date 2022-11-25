<?php

namespace App\Modules\CustomerSettings\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class CustomerInvoiceSettings extends LogModel
{
    use TableFixTrait;

    protected $table = 'customer_invoice_settings';
    protected $primaryKey = 'customer_invoice_settings_id';

    protected $fillable = [
        'company_person_id',
        'delivery_method',
        'active',
        'options',
        'created_date',
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
}
