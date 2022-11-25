<?php

namespace App\Modules\Address\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Address\Models\AddressStoreHours;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;

/**
 * Address Info repository class
 */
class AddressStoreHoursRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container         $app
     * @param AddressStoreHours $addressStoreHours
     */
    public function __construct(Container $app, AddressStoreHours $addressStoreHours)
    {
        parent::__construct($app, $addressStoreHours);
    }

    /**
     * Add new address store hours
     *
     * @param array $input
     *
     * @return AddressStoreHours|AddressStoreHours:static
     */
    public function create(array $input)
    {
        return AddressStoreHours::create($input);
    }

    public function updateOrCreate(array $input)
    {
        return AddressStoreHours::updateOrCreate(['address_id' => $input['address_id']], $input);
    }

    /**
     * Get store opening hours
     *
     * @param $addressId
     *
     * @return array|null
     */
    public function getOpeningHoursByAddressId($addressId)
    {
        $openingHours = $this->model->where('address_id', $addressId)->first();
        if ($openingHours) {
            return $this->parseHours($openingHours);
        }

        return [];
    }


    /**
     * Get store opening hours
     *
     * @param $addressIds
     *
     * @return array|null
     */
    public function getOpeningHoursByAddressIds($addressIds)
    {
        $hours = [];
        $openingHours = $this->model->whereIn('address_id', $addressIds)->get();
        
        foreach ($openingHours as $openingHour) {
            $hours[$openingHour->address_id] = $openingHour;
        }

        return $hours;
    }
    
    /**
     * Parse opening hours
     *
     * @param $openingHours
     *
     * @return array|null
     */
    private function parseHours($openingHours)
    {
        $days = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];

        try {
            $data = [
                'store_name'         => $openingHours->store_name,
                'store_phone_number' => $openingHours->store_phone_number,
                'mall_name'          => $openingHours->mall_name,
                'mall_phone_number'  => $openingHours->mall_phone_number,
                'is_mall'            => (bool)$openingHours->is_mall,
                'saturday_is_open'   => (bool)$openingHours->saturday_is_open,
                'sunday_is_open'     => (bool)$openingHours->sunday_is_open,
                'week_days'          => [],
            ];

            $nullCount = 0;
            foreach ($days as $index => $day) {
                $open = $day . '_open_at';
                $close = $day . '_close_at';

                if (!is_null($openingHours->store_name) && !is_null($openingHours->store_phone_number)) {
                    $dayItem = [
                        'day_name'  => ucfirst($day),
                        'day_hours' => null,
                    ];

                    if ($openingHours->{$open} === '00:00' && $openingHours->{$close} == '24:00') {
                        $dayItem['day_hours'] = 'Open 24 hours';
                    } elseif (!is_null($openingHours->{$open}) && !is_null($openingHours->{$close})) {
                        $dayItem['day_hours'] = date('g:i a', strtotime($openingHours->{$open}))
                            . ' - ' . date('g:i a', strtotime($openingHours->{$close}));
                    } else {
                        $dayItem['day_hours'] = null;
                        ++$nullCount;
                    }

                    if ($nullCount === 7) {
                        $data['week_days'] = [];
                    } else {
                        $data['week_days'][] = $dayItem;
                    }
                }
            }

            if (is_null($data['store_name']) && is_null($data['mall_name'])) {
                return null;
            } else {
                return $data;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
}
