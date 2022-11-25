<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderTemplate
 *
 * @package App\Modules\WorkOrder\Models
 */
class DataExchange extends LogModel
{
    use TableFixTrait;

    protected $table = 'data_exchange';
    protected $primaryKey = 'data_exchange_id';

    public $timestamps = false;

    protected $fillable = [];

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
