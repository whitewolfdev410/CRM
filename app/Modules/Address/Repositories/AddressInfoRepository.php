<?php

namespace App\Modules\Address\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Address\Models\AddressInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;

/**
 * Address Info repository class
 */
class AddressInfoRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container $app
     * @param AddressInfo $address_info
     */
    public function __construct(Container $app, AddressInfo $address_info)
    {
        parent::__construct($app, $address_info);
    }

    /**
     * Add new address info
     *
     * @param array $input
     *
     * @return AddressInfo|AddressInfo:static
     */
    public function create(array $input)
    {
        return AddressInfo::create($input);
    }

    public function updateOrCreate(array $input)
    {
        return AddressInfo::updateOrCreate(['address_id' => $input['address_id']], $input);
    }

    /**
     * Get addresses without address info
     *
     * @param int $limit
     * @return array
     */
    public function getAddressesWithoutInfo($limit = 100)
    {
        $addresses = DB::select(DB::raw('
          SELECT 
            address.address_id,
            address.address_name, 
            address.address_1, 
            address.address_2, 
            address.city, 
            address.state,
            address.country,
            person.custom_1 as company
          FROM
            address
          INNER JOIN
            person ON address.person_id = person.person_id
          WHERE
            address_name != "" AND
            address_id NOT IN (
              SELECT 
                address_id 
              FROM
                address_info
            ) AND
            address_1 IS NOT NULL
          LIMIT ' . (int)$limit));

        return $addresses;
    }
}
