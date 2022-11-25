<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LinkPersonWoStats
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property int       company_person_id
 * @property Carbon    date
 * @property string    duration
 * @property int       duration_time
 * @property int       is_resolved
 * @property int       link_person_wo_id
 * @property int       person_id
 * @property string    region
 * @property string    resolution_memo
 * @property int       resolution_type_id
 * @property string    sch
 * @property int       sch_time
 * @property string    service_route
 * @property string    team
 * @property int       tech_status_type_id
 * @property int       work_order_id
 * @property string    work_order_number
 * @property string    zone
 *
 * @property WorkOrder workOrder
 *
 * @method Builder|EloquentBuilder|LinkPersonWoStats ofPerson(int $personId)
 * @method Builder|EloquentBuilder|LinkPersonWoStats ofWorkOrder(int $workOrderId)
 */
class LinkPersonWoStats extends LogModel
{
    //region Eloquent configurations

    use TableFixTrait;

    public $timestamps = false;

    protected $table = 'link_person_wo_stats';
    protected $primaryKey = 'link_person_wo_stats_id';

    protected $fillable = [];

    //endregion

    //region Constructor

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //endregion

    //region Relationships

    /**
     * Many-to-one relationship with Person
     *
     * @return Builder|BelongsTo
     */
    public function companyPerson()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return Builder|BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id', 'person_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function resolutionType()
    {
        return $this->belongsTo(Type::class, 'resolution_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function statusType()
    {
        return $this->belongsTo(Type::class, 'status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function techStatusType()
    {
        return $this->belongsTo(Type::class, 'tech_status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with WorkOrder
     *
     * @return Builder|BelongsTo
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id', 'work_order_id');
    }

    // @todo recall_link_person_wo_id

    //endregion

    //region Accessors

    /**
     * Get company_person_id data
     *
     * @return int
     */
    public function getCompanyPersonId()
    {
        return $this->company_person_id;
    }

    /**
     * Get date data
     *
     * @return Carbon
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Get duration data
     *
     * @return string
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * Get duration_time data
     *
     * @return int
     */
    public function getDurationTime()
    {
        return $this->duration_time;
    }

    /**
     * Get is_resolved data
     *
     * @return int
     */
    public function getIsResolved()
    {
        return $this->is_resolved;
    }

    /**
     * Set is_resolved data
     *
     * @param int $value
     *
     * @return $this
     */
    public function setIsResolved($value)
    {
        $this->is_resolved = $value;

        return $this;
    }

    /**
     * Get link_person_wo_id data
     *
     * @return int
     */
    public function getLinkPersonWoId()
    {
        return $this->link_person_wo_id;
    }

    /**
     * Get person_id data
     *
     * @return int
     */
    public function getPersonId()
    {
        return $this->person_id;
    }

    /**
     * Get region data
     *
     * @return string
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Get resolution_memo data
     *
     * @return string
     */
    public function getResolutionMemo()
    {
        return $this->resolution_memo;
    }

    /**
     * Set resolution_memo data
     *
     * @param string $value
     *
     * @return $this
     */
    public function setResolutionMemo($value)
    {
        $this->resolution_memo = $value;

        return $this;
    }

    /**
     * Get resolution_type_id data
     *
     * @return int
     */
    public function getResolutionTypeId()
    {
        return $this->resolution_type_id;
    }

    /**
     * Set resolution_type_id data
     *
     * @param int $value
     *
     * @return $this
     */
    public function setResolutionTypeId($value)
    {
        $this->resolution_type_id = $value;

        return $this;
    }

    /**
     * Get sch data
     *
     * @return string
     */
    public function getSch()
    {
        return $this->sch;
    }

    /**
     * Get sch_time data
     *
     * @return int
     */
    public function getSchTime()
    {
        return $this->sch_time;
    }

    /**
     * Get service_route data
     *
     * @return string
     */
    public function getServiceRoute()
    {
        return $this->service_route;
    }

    /**
     * Get team data
     *
     * @return string
     */
    public function getTeam()
    {
        return $this->team;
    }

    /**
     * Get tech_status_type_id data
     *
     * @return int
     */
    public function getTechStatusTypeId()
    {
        return $this->tech_status_type_id;
    }

    /**
     * Get work_order_id data
     *
     * @return int
     */
    public function getWorkOrderId()
    {
        return $this->work_order_id;
    }

    /**
     * Return Work Order
     *
     * @return WorkOrder
     */
    public function getWorkOrder()
    {
        return $this->workOrder;
    }

    /**
     * Get zone data
     *
     * @return string
     */
    public function getZone()
    {
        return $this->zone;
    }

    //endregion

    //region Scopes

    /**
     * @param EloquentBuilder $query
     * @param int             $personId
     *
     * @return Builder|EloquentBuilder|LinkPersonWo $this
     */
    public function scopeOfPerson($query, $personId)
    {
        return $query->where($this->table . '.person_id', '=', $personId);
    }

    /**
     * @param EloquentBuilder $query
     * @param int             $workOrderId
     *
     * @return Builder|EloquentBuilder|LinkPersonWo $this
     */
    public function scopeOfWorkOrder($query, $workOrderId)
    {
        return $query->where($this->table . '.work_order_id', '=', $workOrderId);
    }

    //endregion
}
