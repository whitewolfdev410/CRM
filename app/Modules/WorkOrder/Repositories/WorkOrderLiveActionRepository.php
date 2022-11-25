<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\File\Services\FileService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\Paginator;
use App\Modules\WorkOrder\Models\WorkOrderLiveAction;
use App\Modules\Address\Models\Address;
use App\Modules\Email\Services\EmailSenderService;
use App\Modules\TruckOrder\Models\TruckOrder;
use Illuminate\Database\Eloquent\Collection;
use App\Modules\WorkOrder\Services\WorkOrderFleetmaticsApiService;

/**
 * WorkOrderLiveActionRepository repository class
 */
class WorkOrderLiveActionRepository extends AbstractRepository
{
    const SECONDS_TO_SEND_NEXT_INFO_EMAIL = 1800; //30 minutes
    const COUNT_OF_KM_TO_LEAVE_POINT = 3;

    /**
     * @var WorkOrderFleetmaticsApiService
     */
    private $workOrderFleetmaticsApiService;

    /**
     * Cache for geofence points
     *
     * @var array
     */
    private $geofenceCache = [];

    /**
     * Points actions
     *
     * @var array
     */
    private $pointsActions = ['Delivery', 'Pickup'];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param WorkOrderLiveAction $workOrderLiveAction
     */
    public function __construct(Container $app, WorkOrderLiveAction $workOrderLiveAction)
    {
        parent::__construct($app, $workOrderLiveAction);
    }

    /**
     * Save vehicle live GPS data point
     *
     * @param array $externalVehicle - external vehicle object
     * @param array $truckOrders - collection of truck orders from today
     * @param array $vehicleGPSData - vehicle gps data
     * @param array $vehicleGPSStatus - vehicle gps status
     * @return array|bool
     */
    public function saveData($externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, WorkOrderFleetmaticsApiService $workOrderFleetmaticsApiService)
    {
        $this->workOrderFleetmaticsApiService = $workOrderFleetmaticsApiService;
        $result = [];
        //Set date and set current timezone
        $now = new \DateTime();
        $date = \DateTime::createFromFormat('Y-m-d\TH:i:s', $vehicleGPSData['UpdateUTC'], new \DateTimeZone('UTC'));
        $date->setTimezone($now->getTimezone());

        //If it is today's date
        if ($date->format('Y-m-d') == date('Y-m-d')) {
            $vehicleNumber = $externalVehicle['vehicle_number'];
            $vehicleName = $externalVehicle['vehicle_name'];
            //Get last saved row for vehicle
            $lastRow = $this->model
                ->where('vehicle_number', $vehicleNumber)
                ->whereRaw("DATE(action_date_from) = '{$date->format('Y-m-d')}'")
                ->orderByDesc('action_date_from')//order by latest rows
                ->first();

            //if last rows exists
            if ($lastRow) {
                $dateFrom = \DateTime::createFromFormat('Y-m-d H:i:s', $lastRow->action_date_from);
                $diff = $date->getTimestamp() - $dateFrom->getTimestamp();
                $lastRow->action_date_to = $date->format('Y-m-d H:i:s');
                if ($lastRow->action_date_to == $lastRow->action_date_from) {
                    $lastRow->action_date_to = $now->format('Y-m-d H:i:s');
                    $diff = $now->getTimestamp() - $dateFrom->getTimestamp();
                }

                //Set times
                if ($lastRow->vehicle_status == 'Idle') {
                    $lastRow->idle_time = ceil($diff / 60);
                }
                if ($lastRow->vehicle_status == 'Stop') {
                    $lastRow->stop_time = ceil($diff / 60);
                }
                if ($lastRow->vehicle_status == 'Moving') {
                    $lastRow->moving_time = ceil($diff / 60);
                }
                if ($lastRow->vehicle_status == 'Towing') {
                    $lastRow->towing_time = ceil($diff / 60);
                }
                //set odometer and distance
                $lastRow->odometer_to = round($vehicleGPSStatus['CurrentOdometer'], 2);
                $lastRow->distance = $lastRow->odometer_to - $lastRow->odometer_from;
                //save last row
                $lastRow->save();
                //refresh model
                $lastRow = $lastRow->fresh();
                $result[] = $lastRow;
                //get last rows truck orders
                $lastRowTruckOrders = $this->getTruckOrdersForRow($lastRow);
                //If new record from the GPS is the same as previously record
                if (in_array($lastRow->action_type, $this->pointsActions) && $lastRow->address_line_1 == $vehicleGPSData['Address']['AddressLine1'] &&
                    $lastRow->locality == $vehicleGPSData['Address']['Locality'] && $lastRow->postal_code == $vehicleGPSData['Address']['PostalCode']
                ) {
                    //if row has some truck orders
                    if ($lastRowTruckOrders) {
                        if ($lastRow->last_email_date) {
                            //Check if difference between now and last email date is greater than SECONDS_TO_SEND_NEXT_INFO_EMAIL
                            $lastEmailDate = \DateTime::createFromFormat('Y-m-d H:i:s', $lastRow->last_email_date);
                            $sendCondition = $now->getTimestamp() - $lastEmailDate->getTimestamp() >= self::SECONDS_TO_SEND_NEXT_INFO_EMAIL;
                            if ($sendCondition) {
                                //If the same vehicle statuses then send remind info
                                if ($lastRow->vehicle_status == $vehicleGPSStatus['DisplayState']) {
                                    $this->sendEmail([$lastRow], $lastRowTruckOrders, 'truck_reached_point_remind');
                                } else {
                                    //If have different statuses then create new entry
                                    $newRows = $this->createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, 5);
                                    $result += $newRows;
                                    //if new entry has the same address as last entry then send remind info
                                    if ($newRows[0]->address_id == $lastRow->address_id && $lastRow->distance < self::COUNT_OF_KM_TO_LEAVE_POINT) {
                                        $firstReachedEntry = $this->getFirstEntryForRow($newRows[0]);
                                        $this->sendEmail($newRows, $lastRowTruckOrders, 'truck_reached_point_remind', $firstReachedEntry);
                                    }
                                }
                                //if new entry and last entry statuses are different create new record and copy last_email date
                            } elseif ($lastRow->vehicle_status != $vehicleGPSStatus['DisplayState']) {
                                $newRows = $this->createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, 4);
                                $result += $newRows;
                                foreach ($newRows as $row) {
                                    $row->last_email_date = $lastRow->last_email_date;
                                    $row->save();
                                }
                                if (!in_array($newRows[0]->action_type, $this->pointsActions) && in_array($lastRow->action_type, $this->pointsActions)) {
                                    $firstReachedEntry = $this->getFirstEntryForRow($lastRow);
                                    if ($firstReachedEntry) {
                                        $this->sendEmail($newRows, $lastRowTruckOrders, 'truck_leave_point', $firstReachedEntry);
                                    }
                                }
                            }
                        } else {
                            //If has not last send date then send info that truck reached point
                            if ($lastRow->vehicle_status == $vehicleGPSStatus['DisplayState']) {
                                $this->sendEmail([$lastRow], $lastRowTruckOrders, 'truck_reached_point');
                            } else {
                                $newRows = $this->createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, 3);
                                $result += $newRows;
                                $this->sendEmail($newRows, $lastRowTruckOrders, 'truck_reached_point');
                            }
                        }
                    }
                } else { //if it is not pickup or delivery point
                    $isNew = false; //check if it is new arecord
                    //when statuses are different create new rows and copy last sent date if exists
                    if ($lastRow->vehicle_status != $vehicleGPSStatus['DisplayState']) {
                        $rows = $this->createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, 2);
                        $result += $rows;
                        if ($lastRow->last_email_date) {
                            foreach ($rows as $row) {
                                $row->last_email_date = $lastRow->last_email_date;
                                $row->save();
                            }
                        }
                        //get truck orders for the new entry
                        $rowTruckOrders = $this->getTruckOrdersForRow($rows[0]);

                        if (!in_array($rows[0]->action_type, $this->pointsActions) && in_array($lastRow->action_type, $this->pointsActions)) {
                            $firstReachedEntry = $this->getFirstEntryForRow($lastRow);
                            if ($firstReachedEntry) {
                                $lastRowTruckOrders = $this->getTruckOrdersForRow($firstReachedEntry);
                                $this->sendEmail($rows, $lastRowTruckOrders, 'truck_leave_point', $firstReachedEntry);
                            }
                        }
                        $isNew = true; //it is a new record
                    } else {
                        //get truck orders for last entry
                        $rowTruckOrders = $lastRowTruckOrders;
                        $rows = [$lastRow];
                        $isNew = false; //it is an old record
                    }

                    //Get last send date if exists
                    $lastEmailDate = null;
                    if ($rows[0]->last_email_date) {
                        $lastEmailDate = \DateTime::createFromFormat('Y-m-d H:i:s', $rows[0]->last_email_date);
                    }
                    //If last sent date is set and difference conditions meet or last send date does not exist
                    if (($lastEmailDate && $now->getTimestamp() - $lastEmailDate->getTimestamp() >= self::SECONDS_TO_SEND_NEXT_INFO_EMAIL) || !isset($lastEmailDate)) {
                        //and it is delivery or pickup - send email
                        if (in_array($rows[0]->action_type, $this->pointsActions)) {
                            if (!$isNew) {
                                $firstReachedEntry = $this->getFirstEntryForRow($rows[0]);
                                $this->sendEmail($rows, $rowTruckOrders, 'truck_reached_point_remind', $firstReachedEntry);
                            } else {
                                $this->sendEmail($rows, $rowTruckOrders, 'truck_reached_point');
                            }
                        }
                    }
                }
            } else { //otherwise create new entries and send info email
                $newRows = $this->createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, 1);
                $result += $newRows;
                $newRowTruckOrders = $this->getTruckOrdersForRow($newRows[0]);
                if (in_array($newRows[0]->action_type, $this->pointsActions)) {
                    $this->sendEmail($newRows, $newRowTruckOrders, 'truck_reached_point');
                }
            }
        }

        return $result;
    }

    /**
     * Get truck orders for existing GPS entry
     *
     * @param WorkOrderLiveAction $workOrderLiveAction
     * @return bool|Collection
     */
    private function getTruckOrdersForRow(WorkOrderLiveAction $workOrderLiveAction)
    {
        $dateFrom = \DateTime::createFromFormat('Y-m-d H:i:s', $workOrderLiveAction->action_date_from);
        $dateFromFormatted = $dateFrom->format('Y-m-d');
        //At first get all truck orders ids
        $workOrderLiveActions = WorkOrderLiveAction::select('truck_order_id')
            ->where('vehicle_number', $workOrderLiveAction->vehicle_number)
            ->where('address_id', $workOrderLiveAction->address_id)
            ->where('address_line_1', $workOrderLiveAction->address_line_1)
            ->whereRaw("DATE(action_date_from) = '{$dateFromFormatted}' and truck_order_id is not null")
            ->where('vehicle_status', $workOrderLiveAction->vehicle_status)
            ->get();

        //If are some ids then get truck orders collection
        if ($workOrderLiveActions->count() > 0) {
            //collect all ids
            $ids = [];
            foreach ($workOrderLiveActions as $workOrderLiveAction) {
                $ids[] = $workOrderLiveAction->truck_order_id;
            }
            //get collection
            $truckOrders = TruckOrder::whereIn('truck_order_id', $ids)->get();

            return $truckOrders;
        } else { //otherwise return false

            return false;
        }
    }

    /**
     * Send email to config defined recipients
     *
     * @param WorkOrderLiveAction[] $records - collection of GPS entries
     * @param array $truckOrders - truck orders of GPS entry
     * @param string $template - email template name
     * @param WorkOrderLiveAction|null $firstEntry - it is previous record with the same acion
     */
    private function sendEmail($records, $truckOrders, $template, $firstEntry = null)
    {
        //Set mailer service
        $mailService = app()->make(EmailSenderService::class);
        $title = 'Truck reached point';
        $singleRecord = $records[0];
        $additionalData = [];
        switch ($template) {
            case 'truck_reached_point':
                $arrivalDate = \DateTime::createFromFormat('Y-m-d H:i:s', $singleRecord->action_date_from);
                $additionalData['arrivalDate'] = $arrivalDate->format('d/m/Y H:i');
                $additionalData['arrivalEntry'] = $singleRecord;
                $title = "Truck {$singleRecord->vehicle_name} has reached point {$singleRecord->address_line_1}, {$singleRecord->locality}, {$singleRecord->administrative_area} {$singleRecord->postal_code} at {$arrivalDate->format('d/m/Y H:i')}";
                break;
            case 'truck_reached_point_remind':
                //firstEntry is the address (delivery or pickup) which was reached. It exists when one address has a few statuses e.g Idle, Moving etc.
                if ($firstEntry) {
                    $record = $firstEntry;
                    //set arival date and arrival to date
                    if ($record->getId() == $singleRecord->getId()) {
                        $arrivalDate = \DateTime::createFromFormat('Y-m-d H:i:s', $record->action_date_from);
                    } else {
                        $arrivalDate = \DateTime::createFromFormat('Y-m-d H:i:s', $record->action_date_to);
                    }
                    $arrivalDateTo = \DateTime::createFromFormat('Y-m-d H:i:s', $singleRecord->action_date_to);
                    //counting time there
                    $timeThere = abs(ceil(($arrivalDate->getTimestamp() - $arrivalDateTo->getTimestamp()) / 60));
                    $additionalData['arrivalEntry'] = $firstEntry;
                    //get records with truck orders for the first delivery or pickup record
                    $records = $this->getSimilarActions($firstEntry);
                } else {
                    $record = $singleRecord;
                    $arrivalDate = \DateTime::createFromFormat('Y-m-d H:i:s', $record->action_date_from);
                    $arrivalDateTo = \DateTime::createFromFormat('Y-m-d H:i:s', $record->action_date_to);
                    //counting time there
                    $timeThere = abs(ceil(($arrivalDateTo->getTimestamp() - $arrivalDate->getTimestamp()) / 60));
                    $additionalData['arrivalEntry'] = $singleRecord;
                }

                $additionalData['arrivalDate'] = $arrivalDate->format('d/m/Y H:i');
                $additionalData['recordDate'] = $arrivalDateTo->format('d/m/Y H:i');
                $additionalData['timeThere'] = $timeThere;

                $title = "Truck {$record->vehicle_name} has reached point {$record->address_line_1}, {$record->locality}, {$record->administrative_area} {$record->postal_code} at {$arrivalDate->format('d/m/Y H:i')} and he is still there from {$timeThere} minutes";
                break;
            case 'truck_leave_point':
                //firstEntry is the address (delivery or pickup) which was left
                if ($firstEntry) {
                    //place leave date
                    $leftDate = \DateTime::createFromFormat('Y-m-d H:i:s', $singleRecord->action_date_from);
                    //arrival date
                    $arrivalDate = \DateTime::createFromFormat('Y-m-d H:i:s', $firstEntry->action_date_from);
                    //count time there
                    $timeThere = abs(ceil(($leftDate->getTimestamp() - $arrivalDate->getTimestamp()) / 60));
                    $title = "Truck {$firstEntry->vehicle_name} has left point {$firstEntry->address_line_1}, {$firstEntry->locality}, {$firstEntry->administrative_area} {$firstEntry->postal_code} at {$leftDate->format('d/m/Y H:i')} and was there {$timeThere} minutes";
                    //add data to view
                    $additionalData['leftDate'] = $leftDate->format('d/m/Y H:i');
                    ;
                    $additionalData['arrivalDate'] = $arrivalDate->format('d/m/Y H:i');
                    $additionalData['timeThere'] = $timeThere;
                    $additionalData['singleRecord'] = $singleRecord;
                    $additionalData['arrivalEntry'] = $firstEntry;
                    //get records with truck orders for the first delivery or pickup record
                    $records = $this->getSimilarActions($firstEntry);
                }
                break;
        }
        //send email
        $mailService->sendHtml(
            config('services.fleetmatics.reportEmails'),
            config('email.accounts.0.send.email'),
            $title,
            'emails.notifications.' . $template,
            ['records' => $records, 'truckOrders' => $truckOrders] + $additionalData
        );

        //Save last send date to records
        $date = date('Y-m-d H:i:s');
        foreach ($records as $record) {
            $record->last_email_date = $date;
            $record->save();
        }
    }

    /**
     * Create new GPS entry in database
     *
     * @param \DateTime $date - date of GPS entry
     * @param array $externalVehicle - external vehicle data
     * @param array|Collection $truckOrders - collection of truck orders from today
     * @param array $vehicleGPSData - vehicle gps data
     * @param array $vehicleGPSStatus - vehicle gps status
     * @param array $control - control value is used to debug errors
     * @return array
     */
    private function createNewRow($date, $externalVehicle, $truckOrders, $vehicleGPSData, $vehicleGPSStatus, $control)
    {
        $actionType = null;
        $addressId = null;
        $vehicleNumber = $externalVehicle['vehicle_number'];
        $vehicleName = $externalVehicle['vehicle_name'];
        $now = new \DateTime();
        //prepare data to save
        $createData = [
            'address_id' => $addressId,
            'truck_order_id' => null,
            'vehicle_number' => $vehicleNumber,
            'vehicle_name' => $vehicleName,
            'address_line_1' => $vehicleGPSData['Address']['AddressLine1'],
            'address_line_2' => $vehicleGPSData['Address']['AddressLine2'],
            'locality' => $vehicleGPSData['Address']['Locality'],
            'administrative_area' => $vehicleGPSData['Address']['AdministrativeArea'],
            'postal_code' => $vehicleGPSData['Address']['PostalCode'],
            'country' => $vehicleGPSData['Address']['Country'],
            'delta_distance' => $vehicleGPSData['DeltaDistance'],
            'delta_time' => $vehicleGPSData['DeltaTime'],
            'vehicle_status' => $vehicleGPSStatus['DisplayState'],
            'idle_time' => 0,
            'moving_time' => 0,
            'towing_time' => 0,
            'control' => $control,
            'stop_time' => 0,
            'odometer_from' => round($vehicleGPSStatus['CurrentOdometer'], 2),
            'odometer_to' => round($vehicleGPSStatus['CurrentOdometer'], 2),
            'latitude' => $vehicleGPSData['Latitude'],
            'longitude' => $vehicleGPSData['Longitude'],
            'action_date_from' => $date->format('Y-m-d H:i:s'),
            'action_date_to' => $date->format('Y-m-d H:i:s'),
            'action_type' => $actionType
        ];

        //Set times in minutes ("i")
        $diff = $now->getTimestamp() - $date->getTimestamp();
        if ($vehicleGPSStatus['DisplayState'] == 'Idle') {
            $createData['idle_time'] = ceil($diff / 60);
        }
        if ($vehicleGPSStatus['DisplayState'] == 'Stop') {
            $createData['stop_time'] = ceil($diff / 60);
        }
        if ($vehicleGPSStatus['DisplayState'] == 'Moving') {
            $createData['moving_time'] = ceil($diff / 60);
        }
        if ($vehicleGPSStatus['DisplayState'] == 'Towing') {
            $createData['towing_time'] = ceil($diff / 60);
        }


        //if geofence name exists then look for pickup or delivery and save
        if (isset($vehicleGPSData['GeoFenceName'])) {
            //extract address id from geofence name
            preg_match('/([0-9]+)/im', $vehicleGPSData['GeoFenceName'], $matches);
            if (count($matches)) {
                //parse to int
                $addressIdMatched = (int)end($matches);
                if ($addressIdMatched) {
                    $vehicleGPSData['GeoFenceId'] = $addressIdMatched;
                    //Looking for address
                    $address = Address::where('address_id', '=', $addressIdMatched)->first();
                    if ($address) {
                        $addressId = $address->getId();
                        //geofence id: crm_user + addressId
                        $geofenceId = config('app.crm_user') . $addressId;
                        $createData['address_id'] = $addressId;
                        $result = [];
                        foreach ($truckOrders as $truckOrder) {
                            //if this is point (delivery or pickup) of current truck order save it
                            if (in_array($geofenceId, $truckOrder['workOrderPlaces'])) {
                                $truckOrdersIds[] = $truckOrder['id'];
                                //Can save point for truck order only one time
                                $actionType = $truckOrder['workOrderPlacesFull'][$geofenceId];
                                $createData['action_type'] = $actionType;
                                $createData['truck_order_id'] = $truckOrder['id'];
                                $result[] = WorkOrderLiveAction::create($createData);
                            }
                        }

                        if (!empty($result)) {
                            return $result;
                        }
                    }
                }
            }
        }

        //As default save only one record but return in array
        return [WorkOrderLiveAction::create($createData)];
    }

    /**
     * Get geofence point
     *
     * @param string $geofenceId - fleetmatics geofence id
     * @return array|string
     */
    private function getGeofencePoint($geofenceId)
    {
        if (!isset($this->geofenceCache[$geofenceId])) {
            $this->geofenceCache[$geofenceId] = $this->workOrderFleetmaticsApiService->getGeofenceById($geofenceId);
        }

        return $this->geofenceCache[$geofenceId];
    }

    /**
     * Get first record for current entry
     *
     * @param WorkOrderLiveAction $workOrderLiveAction
     * @param \DateTime|null $date
     * @return null|WorkOrderLiveAction
     */
    private function getFirstEntryForRow(WorkOrderLiveAction $workOrderLiveAction, \DateTime $date = null)
    {
        if (!$date) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $workOrderLiveAction->action_date_from);
        }
        $query = $this->model
            ->where('vehicle_number', $workOrderLiveAction->vehicle_number)
            ->whereRaw("DATE(action_date_from) = '{$date->format('Y-m-d')}'");

        if ($workOrderLiveAction->address_id) {
            $query->where("address_id", $workOrderLiveAction->address_id);
        }
        $query->where("address_line_1", $workOrderLiveAction->address_line_1);
        $query->whereIn("action_type", $this->pointsActions)
            ->orderBy('action_date_from');//order by latest rows

        $result = $query->first();

        return $result ? $result : $workOrderLiveAction;
    }

    /**
     * Get similar actions for main action
     *
     * @param WorkOrderLiveAction $workOrderLiveAction
     * @return Collection
     */
    private function getSimilarActions(WorkOrderLiveAction $workOrderLiveAction)
    {
        $dateFrom = \DateTime::createFromFormat('Y-m-d H:i:s', $workOrderLiveAction->action_date_from);
        $dateFromFormatted = $dateFrom->format('Y-m-d');

        //Gt all similar truck orders actions
        $workOrderLiveActions = WorkOrderLiveAction::where('vehicle_number', $workOrderLiveAction->vehicle_number)
            ->where('address_id', $workOrderLiveAction->address_id)
            ->whereRaw("DATE(action_date_from) = '{$dateFromFormatted}' and truck_order_id is not null")
            ->where('vehicle_status', $workOrderLiveAction->vehicle_status)
            ->groupBy('truck_order_id')
            ->get();

        return $workOrderLiveActions;
    }
}
