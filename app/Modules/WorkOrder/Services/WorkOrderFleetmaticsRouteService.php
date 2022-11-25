<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Address\Models\Address;
use App\Modules\ExternalServices\Common\DevErrorLogger;
use App\Modules\TruckOrder\Models\TruckOrder;
use App\Modules\WorkOrder\Fleetmatics\ReportSaver;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrderAction;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use App\Modules\TruckOrder\Repositories\TruckOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class WorkOrderFleetmaticsRouteService
 * @author Pawel Kazmierczak <kazmij@gmail.com>
 * @package App\Modules\WorkOrder\Services
 *
 * DESCRIPTION OF THIS CLASS WORKS:
 * Add drivers actions from Fleetmatics daily reports to work_order_action table.
 * 1. Download new Fleetmatics daily report (CSV file) using Selenium and "fromDate" and "toDate" interval. As default from current date - 3 days to current date
 * 2. These count of days is configurable in WorkOrderFleetmaticsRouteService::LOOK_DAYS_FORWARD_AND_BACK
 * 3. Parse CSV file into array and group by date and then by vehicle_name
 * 4. When data is prepared then get all truck orders from the same time interval
 * 5. Convert truck orders into array and also group by date
 * 6. Loop all reports data and truck orders inner. Add to report data truck orders if exist some Fleetmatics Geofence address points matches
 * 7. After this loop do again loop reports data which have some work orders and save them into the database
 * 8. If some record exists then update him - data will not be duplicated in the one truck order
 * 9. At the end return count of added/updated records.
 *
 */
class WorkOrderFleetmaticsRouteService
{
    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    /**
     * @var TruckOrderRepository
     */
    protected $truckOrderRepository;

    /**
     * @var ReportSaver
     */
    protected $reportSaver;

    /**
     * Count of days to look for truck order actions
     */
    const LOOK_DAYS_FORWARD_AND_BACK = 3;

    /**
     * Initialize class
     *
     * WorkOrderFleetmaticsRouteService constructor.
     * @param WorkOrderRepository $workOrderRepository
     * @param TruckOrderRepository $truckOrderRepository
     * @param ReportSaver $reportSaver
     */
    public function __construct(WorkOrderRepository $workOrderRepository, TruckOrderRepository $truckOrderRepository, ReportSaver $reportSaver)
    {
        $this->workOrderRepository = $workOrderRepository;
        $this->truckOrderRepository = $truckOrderRepository;
        $this->reportSaver = $reportSaver;
    }

    /**
     * Synchronize reports with work orders
     *
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @return int|null
     */
    public function synchronizeRoutes(\DateTime $dateFrom, \DateTime $dateTo)
    {
        echo "Add routes from dates: " . $dateFrom->format('Y-m-d') . " - " . $dateTo->format('Y-m-d') . "\r\n";
        echo "Prepare data..\r\n";

        //Get report data
        $reportData = $this->getReportData($dateFrom, $dateTo);

        echo "Found " . count($reportData) . " days groups\r\n";

        //Result count
        $result = 0;

        //if reportData is not empty then get work orders
        if (!empty($reportData)) {
            //If dateTo is null set it to now
            if (!$dateTo) {
                $dateTo = new \DateTime();
            }

            echo "Get truck orders from database\r\n";

            //Get workorders using dates from and to
            $startDate = $dateFrom->format('Y-m-d');
            $endDate = $dateTo->format('Y-m-d');
            //Only records where date is between above dates
            $workOrders = $this->truckOrderRepository->prepareTruckOrdersData($dateFrom, $dateTo);
            echo "Found " . count($workOrders) . " truck orders.\r\n";

            //Group work orders by date
            $workOrdersGroupedByDate = array_group_by($workOrders, 'order_date');

            echo "Grouped tuck orders for " . count($workOrdersGroupedByDate) . " days groups.\r\n";
            echo "Looking for matched truck orders and save...";

            //Loop and prepare routes to save to valid work order
            foreach ($workOrdersGroupedByDate as $dateKey => $workOrderGroup) {
                //At the beginning reports data is empty array
                $workOrderReportsData = [];
                //Get to analyse only reports from work order's date range +/- LOOK_DAYS_FORWARD_AND_BACK

                // create date from $dateKey ( report index) to be possible create $StartDate, due to previously error and return false
                $newDateKey = \DateTime::createFromFormat('Y-m-d', $dateKey);
                $startDate = \DateTime::createFromFormat('m/d/Y', $newDateKey->format('m/d/Y'));
                if ($startDate) {
                    //Set start day to check
                    $startDate->sub(new \DateInterval('P' . self::LOOK_DAYS_FORWARD_AND_BACK . 'D'));
                    //Add new reports days
                    for ($i = 0; $i < (2 * self::LOOK_DAYS_FORWARD_AND_BACK); $i++) {
                        if ($i !== 0) {
                            $reportDate = $startDate->add(new \DateInterval('P1D'));
                        } else {
                            $reportDate = $startDate;
                        }

                        $reportKey = $reportDate->format('m/d/Y');
                        if (isset($reportData[$reportKey])) {
                            $workOrderReportsData[] = $reportData[$reportKey];
                        }
                    }
                }

                //if report data exists
                if (count($workOrderReportsData) > 0) {
                    //Loop all routes to find work orders with the best match
                    foreach ($workOrderReportsData as &$workOrderReportData) {
                        foreach ($workOrderReportData as $routeKey => &$route) {
                            //Create field fot work orders if do not exist
                            if (!isset($route['workOrders'])) {
                                $route['workOrders'] = [];
                            }
                            //Loop work orders from this date group and match with route
                            foreach ($workOrderGroup as $workOrder) {
                                //Matches count
                                // Get array keys from WorkOrder due to external_address_id which stored as key.
                                $intersect = array_intersect(array_keys($workOrder['workOrderPlacesFull']), $route['placesIds']);
                                //If some matches exist add to work orders field
                                if ($intersect) {
                                    $route['workOrders'][] = $workOrder;
                                }
                            }
                        }
                    }

                    //Save to the database
                    echo "....";

                    //loop all valid reports and save them
                    foreach ($workOrderReportsData as $workOrderReportData) {
                        foreach ($workOrderReportData as $routeKey => $route) {
                            //loop reports truck orders and save actions
                            foreach ($route['workOrders'] as $routeWorkOrder) {
                                //block used pickup and delivery point for one route
                                $blockedPoints = [];
                                //loop all route items and save them as actions
                                foreach ($route['items'] as $itemKey => $item) {
                                    //Set created/updated data
                                    $createData = [
                                        'work_order_id' => $routeWorkOrder['work_order_id'],
                                        'truck_order_id' => $routeWorkOrder['id'], //truck order id
                                        'vehicle_name' => $item['vehicle_name'],
                                        'travel_time_seconds' => $item['travel_time_seconds'],
                                        'time_there_seconds' => $item['time_there_seconds'],
                                        'idle_time_seconds' => $item['idle_time_seconds'],
                                        'distance' => $item['distance'],
                                        'start_location' => $item['start_location'],
                                        'stop_location' => $item['stop_location'],
                                        'odometer_start' => $item['odometer_start'],
                                        'odometer_end' => $item['odometer_end'],
                                        'action_type' => null,
                                        'departure_at' => null,
                                        'arrival_at' => null,
                                        'start_at' => null
                                    ];

                                    //Add action type if address matches with action addresses
                                    $commonAddresses = array_intersect(array_keys($routeWorkOrder['workOrderPlacesFull']), array_merge($item['start_places'], $item['stop_places']));

                                    if ($commonAddresses) {
                                        $matchedPoint = current($commonAddresses);
                                        //Only point if is not exist in block points table
                                        if (!in_array($matchedPoint, $blockedPoints)) {
                                            $blockedPoints[] = $matchedPoint; //add point to block table
                                            $createData['action_type'] = $routeWorkOrder['workOrderPlacesFull'][$matchedPoint];
                                        }
                                    }

                                    //Set date columns if not empty
                                    if ($item['date']) {
                                        $startAt = \DateTime::createFromFormat('m/d/Y h:i A e', $item['date'] . ' ' . $item['start_time'] . ' ' . $item['start_time_zone']);
                                        if ($startAt) {
                                            $createData['start_at'] = $startAt->format('c');
                                        }
                                        if ($item['departure_time']) {
                                            $departureAt = \DateTime::createFromFormat('m/d/Y h:i A e', $item['date'] . ' ' . $item['departure_time'] . ' ' . $item['start_time_zone']);
                                            if ($departureAt) {
                                                //Sometimes action start in the previous day and we need to check it and diff one day is it is.
                                                if ($departureAt->getTimestamp() < $startAt->getTimestamp()) {
                                                    $startAt->sub(new \DateInterval('P1D'));
                                                    $createData['start_at'] = $startAt->format('c');
                                                }
                                                $createData['departure_at'] = $departureAt->format('c');
                                            }
                                        }
                                    }

                                    if ($item['arrival_time']) {
                                        $arrivalAt = \DateTime::createFromFormat('m/d/Y h:i A e', $item['date'] . ' ' . $item['arrival_time'] . ' ' . $item['arrival_time_zone']);
                                        if ($arrivalAt) {
                                            if (isset($departureAt) && $departureAt) {
                                                //Sometimes action start in the previous day and we need to check it and diff one day is it is.
                                                if ($departureAt->getTimestamp() < $arrivalAt->getTimestamp()) {
                                                    $arrivalAt->sub(new \DateInterval('P1D'));
                                                }
                                                if ($arrivalAt->getTimestamp() < $startAt->getTimestamp()) {
                                                    $arrivalAt->add(new \DateInterval('P1D'));
                                                }
                                            }
                                            $createData['arrival_at'] = $arrivalAt->format('c');
                                        }
                                    }

                                    //Save and return object
                                    $workOrderAction = WorkOrderAction::updateOrCreate([
                                        'work_order_id' => $routeWorkOrder['work_order_id'],
                                        'truck_order_id' => $routeWorkOrder['id'],
                                        'start_location' => $item['start_location'],
                                        'stop_location' => $item['stop_location'],
                                        'travel_time_seconds' => $item['travel_time_seconds']
                                    ], $createData);

                                    //Add to result
                                    $result++;
                                }
                            }
                        }
                    }
                }
            }

            return $result;
        }

        return null;
    }

    /**
     * Prepare CSV data to synchronise
     *
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @return array|null
     */
    private function getReportData(\DateTime $dateFrom, \DateTime $dateTo)
    {
        //Set CSV path
        $csvFile = $this->downloadReportFile($dateFrom, $dateTo);
        //If file exists
        if (file_exists($csvFile)) {
            //Map CSV into array
            $csvArray = array_map('str_getcsv', file($csvFile));

            if (is_array($csvArray) && !empty($csvArray)) {
                //get headers columns
                $headersColumns = current($csvArray);
                //Cut headers row from array
                $csvArray = array_slice($csvArray, 1);
                //Map CSV array and add normalize headers as fields keys
                $csvArray = array_map(function ($entry) use ($headersColumns) {
                    $tmp = [];
                    foreach ($headersColumns as $headerKey => $headersColumn) {
                        $normalizeColumn = Str::slug($headersColumn, '_');
                        $tmp[$normalizeColumn] = $entry[$headerKey];
                    }
                    if (isset($tmp['vehicle_name'])) {
                        //Normalize vehicle name - will be used to group by and must be a valid string
                        $tmp['vehicle_name'] = Str::slug($tmp['vehicle_name'], '_');
                        //Add start places ids as array and remove old field
                        if ($tmp['start_place_ids']) {
                            $startPlaces = explode(',', $tmp['start_place_ids']);
                            $startPlaces = array_map('trim', $startPlaces);
                            $tmp['start_places'] = $startPlaces;
                        } else {
                            $tmp['start_places'] = [];
                        }
                        unset($tmp['start_place_ids']);

                        //Add stop places ids as array and remove old field
                        if ($tmp['stop_place_ids']) {
                            $endPlaces = explode(',', $tmp['stop_place_ids']);
                            $endPlaces = array_map('trim', $endPlaces);
                            $tmp['stop_places'] = $endPlaces;
                        } else {
                            $tmp['stop_places'] = [];
                        }
                        unset($tmp['stop_place_ids']);

                        //Change entry data
                        $entry = $tmp;

                        return $entry;
                    }

                    return null;
                }, $csvArray);

                //Group array by date and then vehicle name
                $csvArray = array_group_by($csvArray, 'date', 'vehicle_name');

                //Add places ids and change array structure - if places ids is empty then false record
                $csvArray = array_map(function ($entry) {
                    $entry = array_map(function ($subentry) {
                        $placesIds = [];
                        $moveItems = [];
                        foreach ($subentry as $subentryKey => &$item) {
                            $placesIds = array_unique(array_merge($placesIds, $item['start_places'], $item['stop_places']));
                            //Remove '--' chars from fields
                            $item = array_map(function ($field) {
                                if (!is_array($field)) {
                                    if (preg_match('/\-\-/im', $field)) {
                                        $field = null;
                                    }
                                }

                                return $field;
                            }, $item);
                            $moveItems[] = $item;
                        }

                        if (empty($placesIds)) {
                            return null;
                        } else {
                            $subentry = [
                                'placesIds' => $placesIds,
                                'items' => $moveItems
                            ];

                            return $subentry;
                        }
                    }, $entry);

                    //Remove empty records from array and records where count of items less than two
                    $entry = array_filter($entry, function ($subentry) {
                        return is_array($subentry) && count($subentry['items']) > 1;
                    });

                    return $entry;
                }, $csvArray);

                return $csvArray;
            }
        }

        return null;
    }

    /**
     * Download report CSV
     *
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @return string
     */
    private function downloadReportFile(\DateTime $dateFrom, \DateTime $dateTo)
    {
        //return path of report file
        return $this->reportSaver->save($dateFrom, $dateTo);
    }
}
