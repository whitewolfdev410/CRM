<?php

namespace App\Modules\History\Models;

use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoryWoPartsStatus extends Model
{
    use TableFixTrait;

    protected $table = 'history_wo_parts_status';
    protected $primaryKey = 'id';
    protected $hidden = ['updated_at'];

    const CREATED_AT = 'date_created';
    const UPDATED_AT = ''; // no updated field in this table

    public $timestamps = false;

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    //region accessors

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
     * Get type_id data
     *
     * @return string
     */
    public function getTypeId()
    {
        return $this->type_id;
    }

    /**
     * Get type_value data
     *
     * @return int
     */
    public function getTypeValue()
    {
        return $this->type_value;
    }


    /**
     * Get changes data
     *
     * @return string
     */
    public function getDateCreated()
    {
        if (isset($this->date_created)) {
            return $this->date_created;
        }

        return '';
    }

    //endregion

    //region relationships

    //endregion
}
