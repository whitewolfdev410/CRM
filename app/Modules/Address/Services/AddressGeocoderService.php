<?php

namespace App\Modules\Address\Services;

use Illuminate\Support\Str;
use App\Core\CommandTrait;
use App\Modules\Address\Exceptions\AddressGeocodingException;
use App\Modules\Address\Exceptions\VerifyAddressGeocodingException;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Models\AddressGeocodingAccuracy;
use App\Modules\Address\Models\AddressVerify;
use Exception;
use Geocoder\Exception\CollectionIsEmpty;
use Geocoder\Provider\OpenCage\OpenCage;
use Geocoder\Query\GeocodeQuery;
use Http\Adapter\Guzzle6\Client;
use Illuminate\Contracts\Container\Container;
use Ivory\HttpAdapter\CurlHttpAdapter;
use stdClass;

class AddressGeocoderService
{
    use CommandTrait;

    /**
     * @var Container
     */
    protected $app;

    /**
     * @var AddressVerify|null
     */
    protected $verifiedAddress = null;

    /**
     * Initialize class.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @param array|Address $address
     *
     * @return array
     * @throws Exception
     */
    public function geocode($address)
    {
        if ($address instanceof Address) {
            $address = $address->toArray();
        }

        if ((!is_array($address))) {
            throw new Exception('Address geocoding error - address should be array');
        }
        $address = (object)$address;

        // we try to find verified address from zip codes table
        $verifiedAddress = $this->getVerifiedAddress($address);
        if (!$verifiedAddress) {
            $this->app->log->warning(
                'No entries in address_verify for given address',
                [
                    'country' => $address->country,
                    'zip_code' => $address->zip_code,
                    'address_id' => $address->id,
                ]
            );
        }
        $this->verifiedAddress = $verifiedAddress;

        return $this->getGeocodingData($address);
    }

    /**
     * Get geocoder provider instance
     *
     * @return OpenCage
     */
    protected function getGeocoder()
    {
        $key = config('services.opencage.key', '');
        $curl = new Client();

        return new OpenCage($curl, $key);
    }

    /**
     * Get geocoding data with more than 1 try
     *
     * @param stdClass $address
     *
     * @return \Geocoder\Model\Address
     * @throws bool
     */
    protected function getGeocodingData(stdClass $address)
    {
        $geocoder = $this->getGeocoder();

        // address name that we verify
        $addressName = $address->address_1;

        // address string that will be used to get geocoded data
        $addressStr = '';

        // decide whether we will use address name to find geocoding
        if ($addressName !== null && trim($addressName) != '' &&
            !$this->isPoBox($address)
        ) {
            $addressStr = $addressName . ', ';
        }

        // run full geocoding
        [$geocodedAddress, $accuracy, $exp, $exp2, $distance] =
            $this->runGeocoding($geocoder, $address, $addressStr);

        // if we have no address yet, we might finally try to geocode without
        // address (only if have any address - if not it means we already tried
        // it)
        if ($geocodedAddress === null && $addressStr != '') {
            [$geocodedAddress, $accuracy, $exp, $exp2, $distance] =
                $this->runGeocoding($geocoder, $address, '');
        }

        // if we have no geocoded address we can't to anything more here, we
        // can only throw exception
        if ($geocodedAddress === null) {
            if ($exp2 !== null) {
                throw $exp;
            } else {
                throw $exp;
            }
        }

        // in case distance was calculated and is greater than maximum distance
        // accuracy is set as too far
        if ($this->verifiedAddress !== null && $distance !== null &&
            $distance > $this->getMaxDistance()
        ) {
            $accuracy = AddressGeocodingAccuracy::TOO_FAR_GPS_LOCATION;
        }

        return [$geocodedAddress, $accuracy];
    }

    /**
     * Get verified address record from predefined zip codes table
     *
     * @param stdClass $address
     *
     * @return AddressVerify|null
     */
    protected function getVerifiedAddress(stdClass $address)
    {
        if ($address->zip_code === null || trim($address->zip_code) == '') {
            return null;
        }
        if ($address->country === null || trim($address->country) == '') {
            return null;
        }

        return AddressVerify::where('country', $address->country)
            ->where('zip_code', $address->zip_code)->first();
    }

    /**
     * Calculate distance between Address record (based on zip code and country)
     * and address that was received from geocoding
     *
     * @param \Geocoder\Model\Address|null $address
     * @return float|null
     */
    protected function calculateDistance(
        \Geocoder\Model\Address $address = null
    ) {
        $distance = null;
        $lat1 = null;
        $long1 = null;
        $lat2 = null;
        $long2 = null;

        // we any of addresses is null, we cannot calculate it
        if ($this->verifiedAddress !== null && $address !== null) {
            $lat1 = $address->getCoordinates()->getLatitude();
            $long1 = $address->getCoordinates()->getLongitude();

            if ($lat1 !== null && $long1 !== null && $lat1 != '' &&
                $long1 !== ''
            ) {
                $lat2 = $this->verifiedAddress->getLatitude();
                $long2 = $this->verifiedAddress->getLongitude();

                $distance = $this->vincentyGreatCircleDistance(
                    $lat1,
                    $long1,
                    $lat2,
                    $long2,
                    $this->getEarthRadiusInMiles()
                );
            }
        }

        // If debug, let's save geocoding data to log. Maybe in future we'll
        // remove it, bit it might be helpful at the moment
        if (config('app.debug', false)) {
            $this->app->log->info('Calculated distance info', [
                'distance' => $distance,
                'lat1' => $lat1,
                'long1' => $long1,
                'lat2' => $lat2,
                'long2' => $long2,
            ]);
        }

        return $distance;
    }

    /**
     * Get Earth radius in miles
     *
     * @return float
     */
    protected function getEarthRadiusInMiles()
    {
        return 3959.0;
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula. @see http://stackoverflow.com/a/10054282/3593996
     *
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     *
     * @return float Distance between points in [m] (same as earthRadius)
     */
    public function vincentyGreatCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000.0
    ) {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) -
                sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) +
            cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);

        return $angle * $earthRadius;
    }

    /**
     * Run geocoding for given address. In case standard geocoding won't succeed
     * it will try to run geocoding without using state
     *
     * @param BingMaps $geocoder
     * @param stdClass $address
     * @param string $addressStr
     *
     * @return array
     * @throws bool
     */
    protected function runGeocoding($geocoder, stdClass $address, $addressStr)
    {
        // first attempt - we will use city, state + zip_code (+ address name
        // if it's not empty and it's not a postal code)
        $addressString = $addressStr . $address->city . ', ' . $address->state
            . ', ' . $address->zip_code;

        $exp = null;
        $geocodedAddress = null;

        try {
            $geocodedAddress =
                $this->doGeocoding($geocoder, $address, $addressString);
        } catch (Exception $e) {
            $exp = $e;
        }

        $distance = null;

        if ($geocodedAddress) {
            $distance = $this->calculateDistance($geocodedAddress);
        }

        // by default we won't repeat geocoding without state
        $repeatWithoutState = false;

        // if there was exception (probably no address found) or for found
        // geocoded data postal code is empty or doesn't match address zip code
        // we will try to geocode without state (it may be invalid)
        if ($exp === null) {
            $geocodedPostalCode = $geocodedAddress->getPostalCode();
            if ($geocodedPostalCode === null ||
                $geocodedPostalCode != $address->zip_code
                || $distance == null || $distance > $this->getMaxDistance()
            ) {
                $repeatWithoutState = true;
            }
        } else {
            $repeatWithoutState = true;
        }

        if ($addressStr == '') {
            $accuracy = AddressGeocodingAccuracy::WITHOUT_ADDRESS;
        } else {
            if ($geocodedAddress &&
                $geocodedAddress->getStreetName() !== null
            ) {
                $accuracy = AddressGeocodingAccuracy::FULL;
            } else {
                $accuracy = AddressGeocodingAccuracy::WITHOUT_ADDRESS;
            }
        }

        $exp2 = null;

        // try to repeat without state when it's needed
        if ($repeatWithoutState) {
            $addressString = $addressStr . $address->city . ', ' .
                $address->zip_code;

            $geocodedAddress2 = null;

            // try to geocode without state
            try {
                $geocodedAddress2 =
                    $this->doGeocoding($geocoder, $address, $addressString);
            } catch (Exception $e) {
                $exp2 = $e;
            }

            // if no exception found and postal code matches zip code or distance
            // is less then distance from frist geocoding it means probably this
            // geocoding is more accurate - we will use this one to get geocoding
            // data
            if ($exp2 === null) {
                $geocodedPostalCode = $geocodedAddress2->getPostalCode();

                $distance2 = $this->calculateDistance($geocodedAddress2);

                if (($geocodedPostalCode == $address->zip_code) ||
                    ($distance === null) ||
                    ($distance2 !== null && $distance2 < $distance)
                ) {
                    if ($geocodedAddress) {
                        $geocodedAddress = $this->matchGeocodedAddress($address, [$geocodedAddress, $geocodedAddress2]);
                    }

                    $distance = $distance2;

                    if ($addressStr == '') {
                        $accuracy =
                            AddressGeocodingAccuracy::WITHOUT_ADDRESS_AND_STATE;
                    } else {
                        if ($geocodedAddress &&
                            $geocodedAddress->getStreetName() !== null
                        ) {
                            $accuracy = AddressGeocodingAccuracy::WITHOUT_STATE;
                        } else {
                            $accuracy =
                                AddressGeocodingAccuracy::WITHOUT_ADDRESS_AND_STATE;
                        }
                    }
                }
            }
        }

        return [$geocodedAddress, $accuracy, $exp, $exp2, $distance];
    }

    /**
     * Get best match from geocoded locations
     * @param  object $address
     * @param  array $locations
     * @return object
     */
    private function matchGeocodedAddress($address, $locations)
    {
        $matches = [];

        $shortZip = explode('-', $address->zip_code)[0];
        $shortAddr = preg_replace('/[^a-zA-Z0-9]+/', '', $address->address_1);
        $city = $address->city;

        foreach ($locations as $i => $location) {
            // least significant matching is done by location index in the list (first = best match)
            $match = -$i;

            // same city
            if (strcasecmp($location->getLocality(), $city) == 0) {
                $match += 100;
            }

            // same zip
            if ($location->getPostalCode() == $shortZip) {
                $match += 1000;
            }

            $locAddr = preg_replace('/[^a-zA-Z0-9]+/', '', $location->getFormattedAddress());

            // (probably) same street address
            if (stripos($locAddr, $shortAddr) !== false) {
                $match += 10000;
            }

            $matches[$match] = $location;
        }

        ksort($matches, SORT_NUMERIC);

        return array_pop($matches);
    }

    /**
     * Get maximum distance (in miles) that is considered as valid distance
     *
     * @return int
     */
    public function getMaxDistance()
    {
        return config('modconfig.address.geocoding.max_distance', 100);
    }

    /**
     * Verifies whether address is PO box
     *
     * @param stdClass $address
     *
     * @return bool
     */
    protected function isPoBox(stdClass $address)
    {
        $adr = $address->address_1;
        if ($adr === null) {
            return false;
        }

        $adr = trim(mb_strtoupper($adr));

        if (Str::startsWith($adr, ['PO BOX', 'P.O. BOX', 'P.O BOX'])) {
            return true;
        }

        return false;
    }

    /**
     * Do geocoding for given address string (either standard or reverse)
     *
     * @param OpenCage $geocoder
     * @param stdClass $address
     * @param $addressString
     * @return \Geocoder\Model\Address
     * @throws Exception
     */
    protected function doGeocoding($geocoder, stdClass $address, $addressString)
    {
        $nrAttempts = 3;

        $attempts = 0;
        $geocoding = null;
        
        if (empty($address->address_1) || empty($address->city) || empty($address->zip_code)) {
            $message = 'Address #' . $address->id .
                ' - geocoding error.Missing address data...';
            $this->app->log->notice($message);
            $this->log($message, 'error');
            
            throw new CollectionIsEmpty();
        }
        
        do {
            $exception = false;
            try {
                if ($address->latitude == '' || $address->longitude == '' ||
                    $address->user_geocoded == 0
                ) {
                    $geocoding = $geocoder->geocodeQuery(GeocodeQuery::create($addressString));
                } else {
                    $geocoding = $geocoder->reverse(
                        $address->latitude,
                        $address->longitude
                    );
                }
            } catch (Exception $e) {
                // if we retry, we show this in output otherwise we don't show it
                // (it will be showed for whole address later)
                if ($attempts + 1 < $nrAttempts) {
                    $message = 'Address #' . $address->id .
                        ' - geocoding error. Retrying...';

                    $this->app->log->notice($message);
                    $this->log($message, 'error');
                }

                $exception = $e;
            }
            ++$attempts;
        } while ($attempts < $nrAttempts && $exception !== false);

        // if there is exception it means in last try there was exception
        // we cannot do more - we want to rethrow it
        if ($exception !== false) {
            /** @var Exception $exception */
            throw $exception;
        }

        // get the best match from the list
        $geocoding = $this->matchGeocodedAddress($address, $geocoding);

        // If debug, let's save geocoding data to log. Maybe in future we'll
        // remove it, bit it might be helpful at the moment
        if (config('app.debug', false)) {
            $this->app->log->info('Geocoded address data for Address #' .
                $address->id, [
                'address_string' => $addressString,
                'geocoded_data' => $geocoding->toArray(),
                ]);
        }

        // return geocoding data
        return $geocoding;
    }
}
