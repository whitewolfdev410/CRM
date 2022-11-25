<?php

namespace App\Modules\History\Models;

use App\Core\Old\TableFixTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed|string table_name
 * @property mixed object_id
 * @property mixed merged_object_id
 */
class MergeHistory extends Model
{
    use TableFixTrait;

    protected $table = 'merge_history';

    protected $hidden = ['updated_at'];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public $timestamps = true;

    protected $fillable = [
        'table_name',
        'object_id',
        'merged_object_id'
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
