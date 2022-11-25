<?php

namespace App\Modules\WorkOrder\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

/**
 * Class LinkLabtechWo
 *
 * @package App\Modules\WorkOrder\Models
 */
class LinkLabtechWo extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_labtech_wo';
    protected $primaryKey = 'link_labtech_wo_id';

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
