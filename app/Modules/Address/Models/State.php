<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class State extends LogModel
{
    use TableFixTrait;

    protected $fillable = ['code', 'name', 'country_id'];

    /**
     * Initialize class and launches table fix
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->initTableFix($attributes);
    }

    // relationships

    // scopes

    // getters
}
