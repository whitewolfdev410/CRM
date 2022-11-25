<?php

namespace App\Modules\Person\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use Illuminate\Support\Facades\Config;

class LinkPersonCompany extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_person_company';
    protected $primaryKey = 'link_person_company_id';

    protected $fillable
        = [
            'person_id',
            'member_person_id',
            'address_id',
            'address_id2',
            'position',
            'position2',
            'start_date',
            'end_date',
            'type_id',
            'type_id2',
            'is_default',
            'is_default2',
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
     * Many to one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function person()
    {
        return $this->belongsTo(
            \App\Modules\Person\Models\Person::class,
            'person_id'
        );
    }

    /**
     * Many to one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function memberPerson()
    {
        return $this->belongsTo(
            \App\Modules\Person\Models\Person::class,
            'member_person_id'
        );
    }

    /**
     * Many to one relationship with Address
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function address()
    {
        return $this->belongsTo(
            \App\Modules\Address\Models\Address::class,
            'address_id'
        );
    }

    /**
     * Many to one relationship with Address
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function address2()
    {
        return $this->belongsTo(
            \App\Modules\Address\Models\Address::class,
            'address_id2'
        );
    }

    /**
     * Many to one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(
            \App\Modules\Type\Models\Type::class,
            'type_id'
        );
    }

    /**
     * Many to one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type2()
    {
        return $this->belongsTo(
            \App\Modules\Type\Models\Type::class,
            'type_id2'
        );
    }




    // getters

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
     * Get member_person_id data
     *
     * @return int
     */
    public function getMemberPersonId()
    {
        return $this->member_person_id;
    }

    /**
     * Get address_id data
     *
     * @return int
     */
    public function getAddressId()
    {
        return $this->address_id;
    }

    /**
     * Get address_id2 data
     *
     * @return int
     */
    public function getAddressId2()
    {
        return $this->address_id2;
    }

    /**
     * Get position data
     *
     * @return string
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Get position2 data
     *
     * @return string
     */
    public function getPosition2()
    {
        return $this->position2;
    }

    /**
     * Get start_date data
     *
     * @return date
     */
    public function getStartDate()
    {
        return $this->start_date;
    }

    /**
     * Get end_date data
     *
     * @return date
     */
    public function getEndDate()
    {
        return $this->end_date;
    }

    /**
     * Get type_id data
     *
     * @return int
     */
    public function getTypeId()
    {
        return $this->type_id;
    }

    /**
     * Get type_id2 data
     *
     * @return int
     */
    public function getTypeId2()
    {
        return $this->type_id2;
    }

    /**
     * Get is_default data
     *
     * @return int
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * Get is_default2 data
     *
     * @return int
     */
    public function getIsDefault2()
    {
        return $this->is_default2;
    }
}
