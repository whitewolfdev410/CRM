<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class LinkPersonWoStats
 *
 * @package App\Modules\WorkOrder\Models
 *
 * @property string    title
 * @property string    description
 * @property string    data
 * @property string    table_name
 * @property int       record_id
 * @property Carbon    date
 * @property int       is_resolved
 * @property Carbon    created_at
 * @property Carbon    updated_at
 * @property int       resolution_type_id
 * @property string    resolution_memo
 * @property string    related_table_name
 * @property int       related_record_id
 * @property int       resolving_user_id
 *
 * @property WorkOrder workOrder
 *
 */
class Exceptions extends LogModel
{
    //region Eloquent configurations

    use TableFixTrait;

    public $timestamps = false;

    protected $table = 'exceptions';
    protected $primaryKey = 'exception_id';

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
     * Many-to-one relationship with Type
     *
     * @return Builder|BelongsTo
     */
    public function resolutionType()
    {
        return $this->belongsTo(Type::class, 'resolution_type_id', 'type_id');
    }


    // @todo recall_link_person_wo_id

    //endregion

    //region Accessors

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
     * Get resolution_type_id data
     *
     * @return int
     */
    public function getResolvingUser()
    {
        return $this->resolving_user_id;
    }

    /**
     * Set resolving_user_id data
     *
     * @param int $value
     *
     * @return $this
     */
    public function setResolvingUser($value)
    {
        $this->resolving_user_id = $value;

        return $this;
    }

    //endregion

    //region Scopes

    //endregion
}
