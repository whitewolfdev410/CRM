<?php

namespace App\Modules\History\Models;

use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoryInsertStatus extends Model
{
    use TableFixTrait;

    protected $table = 'history_insert_status';
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
     * Get table_name data
     *
     * @return int
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * Get insert_table data
     *
     * @return int
     */
    public function getInsertTable()
    {
        return $this->insert_table;
    }

    /**
     * Get insert_column data
     *
     * @return int
     */
    public function getInsertColumn()
    {
        return $this->insert_column;
    }

    /**
     * Get insert_type data
     *
     * @return int
     */
    public function getInsertType()
    {
        return $this->insert_type;
    }

    /**
     * Get last_history_id data
     *
     * @return int
     */
    public function getLastHistoryId()
    {
        return $this->last_history_id;
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
