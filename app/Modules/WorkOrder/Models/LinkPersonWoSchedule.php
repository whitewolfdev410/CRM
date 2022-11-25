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
 * @property int person_id
 * @property string scheduled_date
 * @property string estimated_time
 * @property int is_hard_schedule
 * @property int is_active
 */
class LinkPersonWoSchedule extends LogModel
{
    //region Eloquent configurations

    use TableFixTrait;

    public $timestamps = true;

    protected $table = 'link_person_wo_schedule';
    protected $primaryKey = 'link_person_wo_schedule_id';

    protected $fillable = [
        'link_person_wo_id',
        'person_id',
        'scheduled_date',
        'estimated_time',
        'is_hard_schedule',
        'is_active'
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
