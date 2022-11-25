<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Person\Models\Person;
use App\Modules\Type\Models\Type;
use Illuminate\Database\Schema\Builder;

/**
 * @property int    address_id
 * @property int    rank
 * @property int    trade_type_id
 * @property Type   tradeType
 * @property Person vendor
 * @property int    vendor_person_id
 */
class LinkVendorAddress extends LogModel
{
    use TableFixTrait;

    protected $table = 'link_vendor_address';
    protected $primaryKey = 'link_vendor_address_id';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    protected $fillable = [
        'address_id',
        'vendor_person_id',
        'trade_type_id',
        'rank',
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

    //region getters

    /**
     * Return address_id data
     *
     * @return int
     */
    public function getAddressId()
    {
        return $this->address_id;
    }

    /**
     * Get rank data
     *
     * @return int
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Set rank data
     *
     * @param int $value
     */
    public function setRank($value)
    {
        $this->rank = $value;
    }

    /**
     * Get trade type data
     *
     * @return Type
     */
    public function getTradeType()
    {
        return $this->tradeType;
    }

    /**
     * Get trade_type_id data
     *
     * @return int
     */
    public function getTradeTypeId()
    {
        return $this->trade_type_id;
    }

    /**
     * Get vendor data
     *
     * @return Person
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * Get vendor_person_id data
     *
     * @return int
     */
    public function getVendorPersonId()
    {
        return $this->vendor_person_id;
    }

    //endregion

    //region relationships

    /**
     * Many-to-one relationship with Address
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'address_id');
    }

    /**
     * Many-to-one relationship with Person
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor()
    {
        return $this->belongsTo(Person::class, 'vendor_person_id', 'person_id')
            ->where(function ($query) {
                /** @var Builder $query */
                $query->isVendor();
            });
    }

    /**
     * Many-to-one relationship with Type
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tradeType()
    {
        return $this->belongsTo(Type::class, 'trade_type_id', 'type_id');
    }

    //endregion

    //region scopes
    //endregion
}
