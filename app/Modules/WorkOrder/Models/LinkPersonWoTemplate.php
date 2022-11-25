<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class LinkPersonWoSchedule
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property int link_person_wo_id
 * @property int work_order_template_id
 * @property int person_id
 * @property string qb_info
 * @property int status_type_id
 * @property string type
 * @property string special_type
 * @property string estimated_time
 * @property string send_past_due_notice
 */
class LinkPersonWoTemplate extends LogModel
{
    //region Eloquent configurations

    use TableFixTrait;

    public $timestamps = false;

    protected $table = 'link_person_wo_template';
    protected $primaryKey = 'link_person_wo_template_id';

    protected $fillable = [
        'work_order_template_id',
        'person_id',
        'qb_info',
        'status_type_id',
        'type',
        'special_type',
        'estimated_time',
        'send_past_due_notice',
    ];
    //endregion

    //region Constructor

    /**
     * Initialize class and launches table fix
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //endregion
}
