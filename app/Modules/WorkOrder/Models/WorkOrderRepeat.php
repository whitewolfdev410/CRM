<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class WorkOrderRepeat
 *
 * @property mixed work_order_template_id
 * @property mixed interval_value
 * @property mixed interval_keyword
 * @property mixed reminder
 * @property mixed next_date
 * @property mixed days_in_advance
 * @property mixed number_remaining
 *
 * @package App\Modules\WorkOrder\Models
 */
class WorkOrderRepeat extends LogModel
{
    use TableFixTrait;

    protected $table = 'work_order_repeat';
    protected $primaryKey = 'work_order_repeat_id';

    public $timestamps = false;

    protected $fillable = [
        'work_order_template_id',
        'interval_value',
        'interval_keyword',
        'reminder',
        'next_date',
        'days_in_advance',
        'number_remaining'
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
}
