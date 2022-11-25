<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class Currency extends LogModel
{
    use TableFixTrait;

    protected $table = 'currencies';
    protected $primaryKey = 'id';

    protected $fillable = ['code', 'name'];

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

    /**
     * One-to-many relationship with Country
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function countries()
    {
        return $this->hasMany(__NAMESPACE__ . '\\Country', 'currency', 'code');
    }

    // scopes

    // getters

    /**
     * Get code data
     *
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get name data
     *
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
}
