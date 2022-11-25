<?php

namespace App\Modules\Address\Models;

use App\Core\LogModel;
use App\Core\Old\TableFixTrait;
use App\Modules\Contact\Models\ContactSql;

class AddressVerify extends LogModel
{
    use TableFixTrait;

    protected $table = 'address_verify';
    protected $primaryKey = 'id';

    // don't use timestamps for this table
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

    /**
     * Get zip_code data
     *
     * @return string
     */
    public function getZipCode()
    {
        return $this->zip_code;
    }

    /**
     * Get country data
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Get latitude data
     *
     * @return string
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Get longitude data
     *
     * @return string
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Get city data
     *
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Get state data
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Get county data
     *
     * @return string
     */
    public function getCounty()
    {
        return $this->county;
    }
}
