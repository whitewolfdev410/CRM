<?php

namespace App\Modules\Person\Datasets;

use App\Modules\Address\Models\Address;
use App\Modules\Address\Repositories\AddressRepository;

class TestingPersonDataset extends PersonDataset
{
    /**
     * Sample method to show how to create custom dropdown
     *
     * @return mixed
     */
    public function getCustom1Data()
    {
        $addressRepository = new AddressRepository($this->app, new Address());

        return $addressRepository->pluck('address_name', 'address_id');
    }

    /**
     * Sample method to show how to get custom field value (if it's connected
     * to other table) when getting single record
     *
     * @return array
     */
    public function getCustom1DetailedData()
    {
        return [
            'join' => [
                'address',
                'person.custom_1',
                '=',
                'address.address_id',
            ],
            'columns' => ['address.address_name AS `custom_1_value`'],
        ];
    }
}
