<?php

namespace App\Modules\Address\Services;

use App\Modules\Address\Exceptions\AddressGeocodingException;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Repositories\AddressRepository;
use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use stdClass;

class GoogleMapsAddressGeocodingService
{
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
     * @var Client
     */
    private $client;

    /**
     * Initialize class fields
     *
     * @param Container $app
     * @param AddressRepository $addressRepository
     */
    public function __construct(
        Container $app,
        AddressRepository $addressRepository
    ) {
        $this->addressRepository = $addressRepository;
        $this->app = $app;
        $this->client = guzzleClient();
    }

    /**
     * Set output command
     *
     * @param Command $command
     */
    public function setOutput($command)
    {
        $this->output = $command;
    }

    /**
     * Output line
     *
     * @param  string $string
     *
     * @return void
     */
    private function outputLine($string)
    {
        if ($this->output) {
            $this->output->line($string);
        }
    }

    /**
     * Output error
     * @param  string $string
     * @return void
     */
    private function outputError($string)
    {
        if ($this->output) {
            $this->output->error($string);
        }
    }

    /**
     * @param Address $address
     * @return stdClass
     * @throws Exception
     */
    protected function makeAddressObject(Address $address)
    {
        if ($address instanceof Address) {
            $address = $address->toArray();
        }

        if ((!is_array($address))) {
            throw new Exception('Address geocoding error - address should be array');
        }

        return (object) $address;
    }

    /**
     * @param Address $address
     * @throws Exception
     */
    public function geocode(Address $address)
    {
        $lat = '';
        $lng = '';
        $coordsAccuracy = 80; //@geocode API has 4 types of accuracy: ROOFTOP, RANGE_INTERPOLATED, GEOMETRIC_CENTER, APPROXIMATE

        try {
            $addressObj = $this->makeAddressObject($address);

            $addressName = $addressObj->address_1;
            $city = $addressObj->city;
            $state = $addressObj->state;
            $country = $addressObj->country;
            $addressLine = $addressName . ', ' . $city . ' ' . $state . ', ' . $country;

            $addressLine = urlencode($addressLine);
            $url = 'https://maps.google.com/maps/api/geocode/json?sensor=false&address='.$addressLine .
                '&key=' . config('services.google_maps.key');
            $request = $this->client->get($url);
            $responseData = $request->json();

            if (isset($responseData['status'])
                && $responseData['status'] == 'OK'
                && isset($responseData['results'][0]['geometry']['location'])
            ) {
                $latLongArray = $responseData['results'][0]['geometry']['location'];

                if (isset($latLongArray['lat'])) {
                    $lat = $latLongArray['lat'];
                }
                if (isset($latLongArray['lng'])) {
                    $lng = $latLongArray['lng'];
                }
                if (isset($responseData['results'][0]['geometry']['location_type'])) {
                    $locationType = $responseData['results'][0]['geometry']['location_type'];
                    $coordsAccuracy = $this->setCoordsAccuracy($locationType);
                }
            }

            $valid = $this->isValidLatitudeAndLongitude($lat, $lng);
            if ($valid) {
                $fields['latitude'] = $lat;
                $fields['longitude'] = $lng;
                $fields['coords_accuracy'] = $coordsAccuracy;
                $fields['geocoded'] = Address::GEOCODED;
                $fields['geocoding_data'] = json_encode($responseData);

                $this->addressRepository->simpleUpdate($addressObj->id, $fields);

                $this->outputLine('Address #' . $addressObj->id . ' geocoded');
            }
        } catch (Exception $ex) {
            $this->outputError('Error: ' . $ex->getMessage());
            // set as geocoding error
            $this->addressRepository
                ->simpleUpdate($address->id, ['geocoded' => Address::GEOCODING_ERROR]);

            // log exception
            /** @var AddressGeocodingException $exp */
            $exp = $this->app->make(AddressGeocodingException::class);
            $exp->setData([
                'address_id' => $address['id'],
                'exception'  => (string) $ex,
            ]);
            $exp->log();
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
        return $latitude != '' && $longitude != '';
    }

    /**
     * @param string $locationType
     * @return int
     */
    protected function setCoordsAccuracy($locationType)
    {
        switch ($locationType) {
            case 'ROOFTOP':
                $coordsAccuracy = 99;
                break;

            case 'RANGE_INTERPOLATED':
                $coordsAccuracy = 80;
                break;

            case 'GEOMETRIC_CENTER':
                $coordsAccuracy = 50;
                break;

            case 'APPROXIMATE':
                $coordsAccuracy = 30;
                break;

            default:
                $coordsAccuracy = 0;
                break;
        }

        return $coordsAccuracy;
    }
}
