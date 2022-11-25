<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Address\Models\Address;
use App\Modules\ExternalServices\Common\DevErrorLogger;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Class WorkOrderFleetmaticsApiService
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderFleetmaticsApiService
{
    const USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';

    /**
     * Max status code length in Fleetmatics API
     */
    const MAX_STATUS_CODE_LENGTH = 15;

    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    private $authToken;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var array
     */
    private $statusesCache = null;

    /**
     * @var array
     */
    private $typesCache = null;

    /**
     * @var array
     */
    private $driversCache = null;


    /**
     * Initialize class
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        //Get config data
        $this->fleetmaticsSettings = config('services.fleetmatics', []);
        if (!empty($this->fleetmaticsSettings)) {
            //Create client
            $this->client = guzzleClient([
            'base_uri' => $this->fleetmaticsSettings['apiUrl'],
            'cookies' => true,
            'headers' => [
                'User-Agent' => static::USER_AGENT,
            ],
        ]);
        }
    }

    /**
     * Authorization - get token
     *
     * @return bool
     * @throws \Exception
     */
    public function auth()
    {
        $expiresAt = new \DateTime();
        //Token must be refreshed every 20 minutes (I added 19m)
        $expiresAt->add(new \DateInterval('PT19M'));
        $client = $this->client;
        $fleetmaticsSettings = $this->fleetmaticsSettings;
        $this->authToken = Cache::remember(
            'ApiFleetmaticsToken',
            $expiresAt,
            function () use ($client, $fleetmaticsSettings) {
                try {
                    //Get API token
                    $tokenResult = $client->get('token', [
                        'auth' => [$fleetmaticsSettings['username'], $fleetmaticsSettings['password']],
                    ]);
                    if ($tokenResult->getStatusCode() === 200) {
                        //Set requests headers
                        $token = $tokenResult->getBody()->getContents();

                        return $token;
                    } else {
                        //Save errors and exit
                        app(DevErrorLogger::class)->logError(
                            'fleetmatics',
                            'Cannot auth to Fleetmatics REST API: ' . $tokenResult->getStatusCode()
                        );
                        exit('Can\'t connect with Fleetmatics REST API: ' . $tokenResult->getStatusCode());
                    }
                } catch (RequestException $e) {
                    //Save errors to logs - cannot continue
                    $response = json_decode($e->getResponse()->getBody()->getContents(), true);
                    app(DevErrorLogger::class)->logError(
                        'fleetmatics',
                        'Cannot auth to Fleetmatics REST API: ' . $e->getResponse()->getStatusCode() . ':' . $response['Message']
                    );
                    exit('Can\'t connect with Fleetmatics REST API: ' . $e->getResponse()->getStatusCode() . ':' . $response['Message']);
                }
            }
        );

        if ($this->authToken) {
            $this->headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Atmosphere realm=http://atmosphere,atmosphere_app_id=' . $this->fleetmaticsSettings['appKey'] . ',Bearer ' . $this->authToken,
            ];
        }
    }

    /**
     * Send work orders via API
     *
     * @param $workOrdersData - collection of work orders
     * @return array - array with results
     */
    public function sendWorkOrders($workOrdersData)
    {
        $result = [];
        $saveOrUpdateResult = [];
        foreach ($workOrdersData as $workOrderNumber => $workOrderData) {
            $workOrderId = $workOrderData['workOrderId'];
            $personId = $workOrderData['personId'];
            //This data is not needed to send via API
            unset($workOrderData['workOrderId']);
            unset($workOrderData['personId']);

            try {
                $fleetmaticsWorkOrder = null;
                // Check if work order exists in Fleetmatics
                if (!$workOrderData['isNew']) {
                    $fleetmaticsWorkOrder = $this->getWorkOrder($workOrderData['WorkOrderNumber']);
                }
                //isNew is not longer needed
                unset($workOrderData['isNew']);
                // if no exist then will be saved - "POST"
                if (!is_array($fleetmaticsWorkOrder)) {
                    $resultSend = $this->client->post('/pas/v1/workorders', [
                        'json' => $workOrderData,
                        'headers' => $this->headers,
                    ]);
                    $saveOrUpdateResult[$workOrderData['WorkOrderNumber']] = json_decode(
                        $resultSend->getBody()->getContents(),
                        true
                    );
                    $result[$workOrderData['WorkOrderNumber']] = 'Added';
                } else { // if exist then will be updated
                    // At first will be updated status if it is needed - status has to be updated by POST method (from documentation)
                    if (!isset($fleetmaticsWorkOrder['WorkOrder']['WorkOrderStatusDescription']) || $fleetmaticsWorkOrder['WorkOrder']['WorkOrderStatusDescription'] != $workOrderData['WorkOrderStatusDescription']) {
                        $sendStatus = [
                            'WorkOrderStatusCode' => $workOrderData['WorkOrderStatusCode'],
                            'WorkOrderStatusDescription' => $workOrderData['WorkOrderStatusDescription'],
                            'WorkOrderStatusType' => $workOrderData['WorkOrderStatusType'],
                        ];
                        $resultSend = $this->client->post(
                            '/pas/v1/workorders/' . $workOrderData['WorkOrderNumber'] . '/status',
                            [
                                'json' => $sendStatus,
                                'headers' => $this->headers,
                            ]
                        );
                        $result[$workOrderData['WorkOrderNumber']]['status'] = 'Already exists - updated status';
                        $saveOrUpdateResult[$workOrderData['WorkOrderNumber']]['status'] = json_decode(
                            $resultSend->getBody()->getContents(),
                            true
                        );
                    }
                    // Update Work order metadata - PUT method
                    // Remove statuses and types fields because status and type can't be updated via PUT (from documenation)
                    unset($workOrderData['WorkOrderStatusCode']);
                    unset($workOrderData['WorkOrderStatusDescription']);
                    unset($workOrderData['WorkOrderStatusType']);

                    $resultSend = $this->client->put('/pas/v1/workorders/' . $workOrderData['WorkOrderNumber'], [
                        'json' => $workOrderData,
                        'headers' => $this->headers,
                    ]);
                    $result[$workOrderData['WorkOrderNumber']]['metadata'] = 'Already exists - updated metadata';
                    $saveOrUpdateResult[$workOrderData['WorkOrderNumber']]['metadata'] = json_decode(
                        $resultSend->getBody()->getContents(),
                        true
                    );
                }
                // Save work order sent date to the database
                LinkPersonWo::whereRaw("work_order_id = $workOrderId AND person_id = $personId")
                    ->update(['sent_to_fleetmatics_date' => date('Y-m-d H:i:s')]);
            } catch (RequestException $e) {
                $response = $e->getResponse()->getBody()->getContents();
                //Sometimes request is interrupted e.g by server restart and "sent_to_fleetmatics_date" will not save.but work order is saved in the API.
                if (preg_match('/already exists/', $response)) {
                    LinkPersonWo::whereRaw("work_order_id = $workOrderId AND person_id = $personId")
                        ->update(['sent_to_fleetmatics_date' => date('Y-m-d H:i:s')]);
                } else {
                    app(DevErrorLogger::class)->logError(
                        'fleetmatics',
                        'Cannot add new work order via REST API: ' . $response
                    );
                }

                $result[] = json_decode($response, true);
            }
        }
        //Save result to logs
        Log::info('Fleetmatics Work Orders result: ' . serialize($result));

        return $result;
    }

    /**
     * Get available drivers
     *
     * @param bool $refresh - if true then data will be refreshed
     * @return array|string - collection of drivers or error message
     */
    public function getDrivers($refresh = false)
    {
        try {
            //Get cached drivers or refresh if variable is set
            if (!$this->driversCache || $refresh) {
                $result = $this->client->get('cmd/v1/drivers', [
                    'headers' => $this->headers,
                ]);

                return json_decode($result->getBody()->getContents(), true);
            }

            return $this->driversCache;
        } catch (RequestException $e) {
            app(DevErrorLogger::class)->logError('fleetmatics', 'Cannot get drivers via REST API: ' . $e->getMessage());

            if ($e->getResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents(), true);
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get driver by name from fleetmatics drivers list
     *
     * @param string $driverName - driver name form person table (custom_1 + custom_2)
     * @return string|null - driver number or null if is not found
     */
    public function getDriverByName($driverName)
    {
        $drivers = $this->getDrivers();
        if (!empty($drivers)) {
            //Loop all found drivers to find driver number
            foreach ($drivers as $driver) {
                if (isset($driver['Driver']['FirstName'], $driver['Driver']['LastName'])) {
                    $name = trim($driver['Driver']['FirstName']) . ' ' . trim($driver['Driver']['LastName']);
                    if ($name == $driverName) {
                        return $driver['Driver']['DriverNumber'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get available statuses
     *
     * @param bool $refresh - if true then data will be refreshed
     * @return array|string - collection of statuses or error message
     */
    public function getStatuses($refresh = false)
    {
        try {
            //Get caches statuses or refresh if variable is set
            if (!$this->statusesCache || $refresh) {
                $result = $this->client->get('pas/v1/workorderstatuses', [
                    'headers' => $this->headers,
                ]);
                $this->statusesCache = json_decode($result->getBody()->getContents(), true);
            }

            return $this->statusesCache;
        } catch (RequestException $e) {
            app(DevErrorLogger::class)->logError('fleetmatics', 'Cannot get statuses via REST API: ' . $e->getMessage());

            if ($e->getResponse()) {
                return json_decode($e->getResponse()->getBody()->getContents(), true);
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get status by code - if no exist then will be created
     *
     * @param string $code - status name as "type_value" from the "type" table
     * @return array|bool
     */
    public function getStatus($code)
    {
        $validStatusCode = $this->getFormattedCode($code);
        $statuses = $this->getStatuses();

        //Looking for status in existing statuses
        if (!empty($statuses)) {
            foreach ($statuses as $status) {
                if (!empty($status['WorkOrderStatus'])) {
                    //If status exists return him
                    if ($status['WorkOrderStatus']['WorkOrderStatusCode'] == $validStatusCode) {
                        return $status['WorkOrderStatus'];
                    }
                }
            }
        }
        try {
            //Create new Fleetmatics status
            $result = $this->client->post('pas/v1/workorderstatuses', [
                'json' => [
                    'WorkOrderStatusCode' => $validStatusCode,
                    'WorkOrderStatusDescription' => $code,
                    'WorkOrderStatusType' => 'None',
                ],
                'headers' => $this->headers,
            ]);

            $status = json_decode($result->getBody()->getContents(), true);
            //Refresh statuses cache
            $this->getStatuses(true);

            return $status;
        } catch (RequestException $e) {
            app(DevErrorLogger::class)->logError('fleetmatics', 'Cannot get status via REST API: ' . $e->getMessage());
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            $message = $response['Message'];
            //if status already exists then return code
            if (preg_match('/already exists/', $message)) {
                return $code;
            }

            return false;
        }
    }

    /**
     * Get status type - if no exist then will be created
     *
     * @param string $code - type name as "type_value" from  the "type" table
     * @return string|bool - type name or false
     */
    public function getType($code)
    {
        //Get existing types
        $types = $this->getTypes();

        //Looking for type in existing types
        if (!empty($types)) {
            foreach ($types as $type) {
                if ($type['Code'] == $code) {
                    return $type['Code'];
                }
            }
        }

        try {
            //Create new type in Fleetmatics
            $result = $this->client->post('pas/v1/workordertypes', [
                'json' => [
                    'Code' => $code,
                    'Description' => $code,
                ],
                'headers' => $this->headers,
            ]);

            $type = json_decode($result->getBody()->getContents(), true);
            //Refresh types
            $this->getTypes(true);

            return $type['Code'];
        } catch (RequestException $e) {
            $response = json_decode($e->getResponse()->getBody()->getContents(), true);
            $message = $response['Message'];
            //If type already exists then return code
            if (preg_match('/already exists/', $message)) {
                return $code;
            }
            app(DevErrorLogger::class)->logError('fleetmatics', 'Cannot get type via REST API: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get available types and cache them
     *
     * @param bool $refresh -if true then data will be refreshed
     * @return array|string - collection of types or error string
     */
    public function getTypes($refresh = false)
    {
        try {
            if (!$this->typesCache || $refresh) {
                $result = $this->client->get('pas/v1/workordertypes', [
                    'headers' => $this->headers,
                ]);
                //Cache found types
                $this->typesCache = json_decode($result->getBody()->getContents(), true);
            }

            return $this->typesCache;
        } catch (RequestException $e) {
            app(DevErrorLogger::class)->logError('fleetmatics', 'Cannot get types via REST API: ' . $e->getMessage());

            return json_decode($e->getResponse()->getBody()->getContents(), true);
        }
    }

    /**
     * Get work order from the API by number
     *
     * @param int $number - work order number from the database
     * @return array|string - array if work orders is exist, otherwise error string
     */
    public function getWorkOrder($number)
    {
        try {
            //Get work order by work order number
            $result = $this->client->get('pas/v1/workorders/' . $number, [
                'headers' => $this->headers,
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->getCode() != 404) {
                app(DevErrorLogger::class)->logError(
                    'fleetmatics',
                    'Cannot get work order ' . $number . ' via REST API: ' . $e->getMessage()
                );
            }

            if ($e->getResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get formated status code for Fleetmatics - max length for API is 15 chars.
     *
     * @param string $code
     * @return string - formatted code
     */
    private function getFormattedCode($code)
    {
        $codeArr = explode(' ', $code);
        $formattedCode = '';
        foreach ($codeArr as $codePart) {
            $codePart = trim($codePart);
            $codePartLength = strlen($codePart);
            if ($codePartLength > 1 && strlen($formattedCode) + $codePartLength <= self::MAX_STATUS_CODE_LENGTH) {
                $formattedCode .= ucfirst($codePart);
            } else {
                break;
            }
        }

        return $formattedCode;
    }

    /**
     * Get Fleetmatics existing vehicles
     *
     * @return string
     */
    public function getVehicles()
    {
        try {
            $result = $this->client->get('cmd/v1/vehicles', [
                'headers' => $this->headers,
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get vehicle GPS data by number
     *
     * @param string $vehicleNumber
     * @return array|string
     */
    public function getVehicleGPSData($vehicleNumber)
    {
        try {
            $result = $this->client->get('rad/v1/vehicles/' . $vehicleNumber . '/location', [
                'headers' => $this->headers,
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get vehicle GPS status data by number
     *
     * @param string $vehicleNumber
     * @return array|string
     */
    public function getVehicleGPSStatus($vehicleNumber)
    {
        try {
            $result = $this->client->get('rad/v1/vehicles/' . $vehicleNumber . '/status', [
                'headers' => $this->headers,
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Get geofence place by its id
     *
     * @param string $placeId - geofence place id
     * @return array|string - array data or string when API returns an error
     */
    public function getGeofenceById($placeId)
    {
        try {
            $result = $this->client->get('geo/v1/geofences/' . $placeId, [
                'headers' => $this->headers,
            ]);

            return json_decode($result->getBody()->getContents(), true);
        } catch (RequestException $e) {
            if ($e->getResponse()) {
                return $e->getResponse()->getBody()->getContents();
            } else {
                return $e->getMessage();
            }
        }
    }

    /**
     * Send system addresses to Fleetmatics
     *
     * @param Collection $addresses
     * @return array
     */
    public function sendAddresses(Collection $addresses)
    {
        $result = [];
        foreach ($addresses as $address) {
            //if external id is not null - try to update address
            if ($address->external_address_id) {
                //if external address update date is null then create address
                if (!$address->date_external_updated) {
                    $created = $this->createAddress($address);
                    $result[$address->getId()] = $created;
                } else { //otherwise update address if system modified date is greater than exteranal update date
                    $dateModified = \DateTime::createFromFormat('Y-m-d H:i:s', $address->date_modified);
                    $dateExternalModified = \DateTime::createFromFormat('Y-m-d H:i:s', $address->date_external_updated);
                    if ($dateModified->getTimestamp() > $dateExternalModified->getTimestamp()) {
                        $result[$address->getId()] = $this->updateAddress($address);
                    } else { //can't update - skip address
                        continue;
                    }
                }
            } else {
                //Create address
                $result[$address->getId()] = $this->createAddress($address);
            }
        }

        return $result;
    }

    /**
     * Create new address in Fleetmatics system
     *
     * @param Address $address -addres object
     * @return string
     */
    private function createAddress(Address $address)
    {
        try {
            $addressData = $this->getAddressJsonData($address); //get address data
            //If address data is not empty
            if ($addressData) {
                //Create new address in Fleetmatics - using API data format.
                $requestData = $this->client->post('geo/v1/geofences/circles', [
                    'json' => $addressData,
                    'headers' => $this->headers,
                ]);
                //decode result
                $result = json_decode($requestData->getBody()->getContents(), true);
                //Save external id and updated date into the database
                $address->external_address_id = $result['PlaceId'];
                $address->date_external_updated = date('Y-m-d H:i:s');
                $address->save();
                //response info
                $response = 'Place #' . $result['PlaceId'] . ' has been created.';
            } else {
                //Skip address in the next command runs - external_unable_to_resolve = 1
                $response = 'Skipped - address is empty!';
                $address->external_unable_to_resolve = 1;
                $address->save();
            }
        } catch (RequestException $e) {
            //If an error occurred then return its.
            $result = json_decode($e->getResponse()->getBody()->getContents(), true);
            $response = 'Created error ' . $e->getCode() . ': ' . $result['Message'];
            //Sometimes record may be existing - so update external id and updated date
            if (preg_match('/already exists/', $response)) {
                $address->external_address_id = config('app.crm_user') . $address->getId();
                $address->date_external_updated = date('Y-m-d H:i:s');
                $address->save();
            } elseif (preg_match('/Unable to resolve/', $response)) {
                $address->external_unable_to_resolve = 1;
                $address->save();
            }
        } catch (\Exception $e) {
            //If error return its
            $response = 'Created error: Place can\'t been created: ' . $e->getCode() . ' - ' . $e->getMessage();
            $address->external_unable_to_resolve = 1;
            $address->save();
        }

        return $response;
    }

    /**
     * Update Fleetmatics address and update external address update date
     *
     * @param Address $address
     * @return string
     */
    private function updateAddress(Address $address)
    {
        try {
            $addressData = $this->getAddressJsonData($address); //get address data
            //If address data is not empty
            if ($addressData) {
                //Create new address in Fleetmatics
                $requestData = $this->client->put('geo/v1/geofences/circles/' . $address->external_address_id, [
                    'json' => $this->getAddressJsonData($address), //get address data
                    'headers' => $this->headers,
                ]);
                //Save updated date into the database
                $address->date_external_updated = date('Y-m-d H:i:s');
                $address->save();
                //response info
                $response = 'Place #' . $address->external_address_id . ' has been updated.';
            } else {
                //Skip address in the next command runs - external_unable_to_resolve = 1
                $response = 'Skipped - address is empty!';
                $address->external_address_id = null;
                $address->external_unable_to_resolve = 1;
                $address->save();
            }
        } catch (RequestException $e) {
            //If error return its
            $response = 'Updated error: Place #' . $address->external_address_id . ' can\'t been updated: ' . $e->getCode() . ' - ' . $e->getMessage();
        } catch (\Exception $e) {
            //If error return its
            $response = 'Updated error: Place #' . $address->external_address_id . ' can\'t been updated: ' . $e->getCode() . ' - ' . $e->getMessage();
        }


        return $response;
    }

    /**
     * Get address data in Fleetmatics format
     *
     * @param Address $address
     * @return array -address data
     */
    private function getAddressJsonData(Address $address)
    {
        //City is required field - if city is null or '' then return false and skip this address processing - set external_unable_to_resolve = 1 and save in DB.
        if (strlen($address->getCity()) > 0) {
            $jsonData = [
                'RadiusInKm' => 1,
                'PlaceId' => config('app.crm_user') . $address->getId(), //create place id - crm_user + $addersId
                'GeoFenceName' => trim($address->getCity()) . ' ' . $address->getId(), //set external address name: city + id
                'IsShownOnMap' => true, //show address on map
                'IsShownOnReport' => true, //and show in reports
            ];

            //Set address
            if ($address->getAddress1()) {
                $country = $address->getCountry() ? trim($address->getCountry()) : false;
                $jsonData['Address'] = [
                    'AddressLine1' => trim($address->getAddress1()),
                    'Locality' => trim($address->getCity()),
                    'AdministrativeArea' => trim($address->getState()),
                    'PostalCode' => trim($address->getZipCode()),
                    //Set country and fix it if is shorter then 2 chars - Fleetmatics needs min 2 chars
                    'Country' => $country && strlen($country) >= 2 ? $country : 'USA',
                ];
            }

            //Set latitude and longitude if exist
            if ($address->getLatitude() && $address->getLongitude() && $address->getGeocoded()) {
                $jsonData['Latitude'] = trim($address->getLatitude());
                $jsonData['Longitude'] = trim($address->getLongitude());
            }

            return $jsonData;
        } else {
            return false;
        }
    }
}
