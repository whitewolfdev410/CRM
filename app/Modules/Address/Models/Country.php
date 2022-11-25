<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;

class Country extends LogModel
{
    use TableFixTrait;


    protected $fillable = [
        'code',
        'name',
        'orderby',
        'phone_prefix',
        'currency',
    ];

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
     * One-to-many relation with Address
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function address()
    {
        return $this->hasMany(__NAMESPACE__ . '\\Address', 'country', 'code');
    }


    /**
     * Many-to-one relation with Currency
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currencyRel()
    {
        return $this->belongsTo(
            __NAMESPACE__ . '\\Currency',
            'currency',
            'code'
        );
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

    /**
     * Get orderby data
     *
     * @return mixed
     */
    public function getOrderby()
    {
        return $this->orderby;
    }
}
