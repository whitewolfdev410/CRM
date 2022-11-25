<?php

namespace App\Modules\Address\Services;

use App\Core\CommandTrait;
use App\Modules\Address\Exceptions\AddressGeocodingException;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Repositories\AddressRepository;
use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;

/**
 * Class AddressGeocodingService
 *
 * @package App\Modules\Address\Services
 */
class AddressGeocodingService
{
    use CommandTrait;

    /**
     * @var AddressRepository
     */
    protected $addressRepository;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Container
     */
    private $app;

    /**
     * @var AddressGeocoderService
     */
    private $service;

    /**
     * Initialize class fields
     *
     * @param Container              $app
     * @param AddressRepository      $addressRepository
     * @param Config                 $config
     * @param AddressGeocoderService $service
     */
    public function __construct(
        Container $app,
        AddressRepository $addressRepository,
        Config $config,
        AddressGeocoderService $service
    ) {
        $this->addressRepository = $addressRepository;
        $this->config = $config;
        $this->app = $app;
        $this->service = $service;
    }

    /**
     * Execute
     *
     * @param       $job
     * @param array $data
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function fire($job, $data)
    {
        $this->geocode($data);

        $job->delete();
    }

    /**
     * Geocode given address
     *
     * @param array $address
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function geocode(array $address)
    {
        if ($this->terminal) {
            $this->service->setTerminal($this->terminal);
        }

        $fields = [];

        // get geocoding data
        try {
            // @todo
            // verify if we have this city/zip_code/country record in address_verify
            // if not - at the moment we mark this as won't geocode and will
            // need to fix those addresses before running geocode for them again

            // Get & normalize city name
            $city = trim($address['city']);
            if (strtoupper($city) == $city) {
                $city = ucwords(strtolower($city));
            }

            // Get & normalize zip_code
            $zip_code = trim($address['zip_code']);
            if (strpos($zip_code, '-') !== false) {
                // USA xxxxx-xxxx zip code.
                // We're only interested in the first group of digits
                $zip_code_array = explode('-', $zip_code, 2);
                $zip_code = $zip_code_array[0];
            }

            $country = trim($address['country']);
            /*
            $addressVerifyRecord = AddressVerify::where('city', $city)
                ->where('zip_code', $zip_code)
                ->where('country', $country)->first();
            if (!$addressVerifyRecord) {
                $fields['geocoded'] = Address::WONT_GEOCODE;
                $this->addressRepository->simpleUpdate($address['id'], $fields);
                $this->log("Address #{$address['id']} is bad: ".
                           "city '$city' doesn't match zip_code '". $zip_code ."'".
                           ($address['country'] == 'US' ? '' : " in {$address['country']}"), 'error');
                return;
            }
            */
            /**  @var \Geocoder\Model\Address $geocoding */
            [$geocoding, $accuracy] = $this->service->geocode($address);

            // if latitude and longitude filled and NOT user geocoded
            // set latitude and longitude + coords_accuracy

            $latitude = $geocoding->getCoordinates()->getLatitude();
            $longitude = $geocoding->getCoordinates()->getLongitude();

            if ($address['user_geocoded'] == 0
                && $this->isValidLatitudeAndLongitude($latitude, $longitude)
            ) {
                $fields['latitude'] = $latitude;
                $fields['longitude'] = $longitude;
                $fields['coords_accuracy'] = $accuracy;
            } else {
                $fields['coords_accuracy'] = 0;
            }
            // mark this address as geocoded
            $fields['geocoded'] = Address::GEOCODED;

            // set geocoding data to address record
            if ($geocoding) {
                $fields['geocoding_data'] = json_encode($geocoding->toArray());
            }

            $this->addressRepository->simpleUpdate($address['id'], $fields);

            $this->log('Address #' . $address['id'] . ' has been geocoded');

            return $fields;
        } catch (Exception $e) {
            $this->app->log->error('Address #' . $address['id'] .
                " - geocoding error. Won't retry anymore in this run");
            $this->log('Address #' . $address['id'] . ' - there was a ' .
                'problem with geocoding (details in general log file).', 'error');

            // set as geocoding error
            $this->addressRepository
                ->simpleUpdate($address['id'], ['geocoded' => Address::GEOCODING_ERROR]);

            // log exception
            /** @var AddressGeocodingException $exp */
            $exp = $this->app->make(AddressGeocodingException::class);
            $exp->setData([
                'address_id' => $address['id'],
                'exception'  => (string)$e,
            ]);
            $exp->log();

            return false;
        }
    }

    /**
     * Get address coordinates
     * @param  Address $address
     * @param  bool $force force to always geocode
     * @return array|null
     */
    public function getCoordinates(Address $address, $force = false)
    {
        if (!$force && $address->geocoding_data) {
            $geoData = json_decode($address->geocoding_data, true);

            // check if address is already geocoded by the geocoding_data

            if (isset($geoData['postalCode'], $geoData['latitude'], $geoData['longitude']) && $geoData['postalCode'] == $address->zip_code) {
                // already geocoded

                return [
                    'latitude' => $geoData['latitude'],
                    'longitude' => $geoData['longitude']
                ];
            }
        }

        // try to geocode if not geocoded

        $geoResult = $this->geocode($address->toArray());

        if ($geoResult && !empty($geoResult['latitude']) && !empty($geoResult['longitude'])) {
            return [
                'latitude' => $geoResult['latitude'],
                'longitude' => $geoResult['longitude'],
            ];
        }
    }

    /**
     * Verify whether given latitude and valid are set
     *
     * @param float $latitude
     * @param float $longitude
     *
     * @return bool
     */
    protected function isValidLatitudeAndLongitude($latitude, $longitude)
    {
        return (isset($latitude) && isset($longitude)
            && $latitude != '' && $longitude != '');
    }
}
