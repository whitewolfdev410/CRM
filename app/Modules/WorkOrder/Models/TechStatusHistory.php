<?php

namespace App\Modules\WorkOrder\Models;

use Illuminate\Database\Eloquent\Model;

class TechStatusHistory extends Model
{
    protected $table = 'tech_status_history';
    protected $primaryKey = 'id';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at'; // not added and used

    protected $fillable = [
        'link_person_wo_id',
        'previous_tech_status_type_id',
        'current_tech_status_type_id',
        'changed_at',
    ];

    // relationships
    //
    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function linkPersonWo()
    {
        return $this->belongsTo(LinkPersonWo::class, 'link_person_wo_id', 'link_person_wo_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function previousTechStatusType()
    {
        return $this->belongsTo(\App\Modules\Type\Models\Type::class, 'previous_tech_status_type_id', 'type_id');
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentTechStatusType()
    {
        return $this->belongsTo(\App\Modules\Type\Models\Type::class, 'current_tech_status_type_id', 'type_id');
    }

    // getters

    /**
     * Return link person wo ID
     * @return integer
     */
    public function getLinkPersonWoId()
    {
        return $this->link_person_wo_id;
    }

    /**
     * Return previous status type ID
     * @return integer
     */
    public function getPreviousTechStatusTypeId()
    {
        return $this->previous_tech_status_type_id;
    }

    /**
     * Return current status type ID
     * @return integer
     */
    public function getCurrentTechStatusTypeId()
    {
        return $this->current_tech_status_type_id;
    }

    /**
     * Return date/time of change between statuses
     * @return Carbon\Carbon
     */
    public function getChangedAt()
    {
        return $this->changed_at;
    }
}
