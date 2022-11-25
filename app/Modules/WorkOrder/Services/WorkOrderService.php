<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\CalendarEvent\Repositories\CalendarEventRepository;
use App\Modules\Contact\Repositories\ContactRepository;
use App\Modules\Email\Models\Email;
use App\Modules\Email\Repositories\EmailThreadWorkOrderRepository;
use App\Modules\ExternalServices\Services\ServiceChannel\Exceptions\ApiErrorException;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\LinkPersonCompanyRepository;
use App\Modules\Person\Repositories\PersonDataRepository;
use App\Modules\WorkOrder\Http\Requests\TechnicianSummaryRequest;
use App\Modules\WorkOrder\Http\Requests\WorkOrderMobileStoreRequest;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkOrderService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var WorkOrderRepository
     */
    protected $workOrderRepository;

    public function __construct(Container $app, WorkOrderRepository $workOrderRepository)
    {
        $this->app = $app;
        $this->workOrderRepository = $workOrderRepository;
    }

    /**
     * @param $workOrderId
     *
     * @return int|null
     */
    public static function getCreatorPersonId($workOrderId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = WorkOrder::find($workOrderId);
        if ($workOrder) {
            return $workOrder->getCreatorPersonId();
        }

        return null;
    }
    
    /**
     * @param $workOrderId
     *
     * @return int|null
     */
    public static function getCompanyPersonId($workOrderId)
    {
        /** @var WorkOrder $workOrder */
        $workOrder = WorkOrder::find($workOrderId);
        if ($workOrder) {
            return $workOrder->getCompanyPersonId();
        }
        
        return null;
    }

    /**
     * Create work order based on email
     *
     * @param  Email  $email
     *
     * @return bool
     */
    public function createWorkOrderByEmail(Email $email)
    {
        try {
            $email->refresh();

//            echo $email->getFromEmail() . "\n";

            $workOrderId = 0;
            if (preg_match("/\{\{(\d+)\}\}/", $email->getSubject(), $matches)) {
                $workOrderId = intval($matches[1]);
            } elseif (preg_match("/Work Order #(\d+)/", $email->getSubject(), $matches)) {
                $workOrderId = intval($matches[1]);
            }

//            echo $email->getSubject() . " {$workOrderId}\n";

            if (preg_match_all(
                "/<(\w+)@([\w-]+\.\w+)>/",
                $email->getFromEmail(),
                $matches,
                PREG_SET_ORDER
            ) && count($matches[0]) != 3) {
                if (preg_match_all(
                    "/<(\w+\.\w+)@([\w-]+\.\w+)>/",
                    $email->getFromEmail(),
                    $matches,
                    PREG_SET_ORDER
                ) && count($matches[0]) != 3) {
                    echo "ERROR unable to aquire sender address\n";

                    return false;
                }
            }

            $emailAddress = trim($matches[0][0], '<>');
            $emailDomain = $matches[0][2];

//            echo $emailAddress.' / '.$emailDomain."\n";

            /** @var ContactRepository $contactRepository */
            $contactRepository = app(ContactRepository::class);
            $contact = $contactRepository->getCompanyAndAddressByEmailAddress($emailAddress);
            if ($contact->companyId && preg_match(
                '/spfdomain=(.*)(((\s)dkim)|\);)/msU',
                $email->getHeaders(),
                $matches
            )) {
                $domain = explode(' ', $matches[1]);
                $value = '';
                if (count($domain) == 1) {
                    $value = $emailDomain;
                } elseif (count($domain) > 0) {
                    $value = $domain[0];
                }

                if ($value != '') {
                    if (preg_match('/\s([^\s^=,.]*@'.$value.')/msU', $email->getHeaders(), $matches)) {
                        $contact = $contactRepository->getCompanyAndAddressByEmailAddress($matches[1]);
                    }
                }
            }

            $requestedBy = $contactRepository->getPersonNameByEmailAddress($emailAddress);
            if (!$contact->companyId) {
                $contact = $contactRepository->getCompanyAndAddressByDomain($emailDomain);
            }

            //if company found but no address, select default address
            if ($contact->companyId) {
                if (!$contact->addressId) {
                    /** @var AddressRepository $addressRepository */
                    $addressRepository = app(AddressRepository::class);

                    $contact->addressId = $addressRepository->getCompanyDefaultAddress($contact->companyId);
                }

//                echo "COMPANY ID: $contact->companyId \r\nADDRESS ID: $contact->addressId\r\nWO ID: $workOrderId\r\n";


                if (!$workOrderId) {
                    $expectedCompletionDate = Carbon::parse($email->getDate())->addDays(2)->format('Y-m-d');
                    $viaTypeId = getTypeIdByKey('via.email');

                    $subject = str_replace(["\n\n", "\r\n\r\n"], ["\n", "\r\n"], trim($email->getSubject()));
                    $body = str_replace(["\n\n", "\r\n\r\n"], ["\n", "\r\n"], trim($email->getBodyPlain()));
                    if (empty($body)) {
                        $bodyPlain = htmlspecialchars(trim(strip_tags($email->getBodyHtml())));
                        $body = str_replace(["\n\n", "\r\n\r\n"], ["\n", "\r\n"], trim($bodyPlain));
                    }

                    try {
                        $workOrderData = [
                            'work_order_number'         => 'tmp',
                            'company_person_id'         => $contact->companyId,
                            'billing_company_person_id' => $contact->companyId,
                            'shop_address_id'           => $contact->addressId,
                            'fin_loc'                   => '0',
                            'received_date'             => $email->getDate(),
                            'expected_completion_date'  => $expectedCompletionDate,
                            'via_type_id'               => $viaTypeId,
                            'completion_code'           => '0',
                            'not_to_exceed'             => 0,
                            'created_date'              => now(),
                            'trade_type_id'             => 0,
                            'description'               => "Subject: {$subject} \n-----\n {$body}",
                            'requested_by'              => $requestedBy ?? ''
                        ];
                        
                        if(isCrmUser('fs')) {
                            $workOrderData['subject'] = $email->getSubject();
                        }
                        
                        /** @var WorkOrder $workOrder */
                        $workOrder = WorkOrder::forceCreate($workOrderData);

                        $workOrderId = $workOrder->getId();

                        $workOrder->work_order_number = $workOrderId;
                        $workOrder->save();

                        //                        echo "WO created\n";
                    } catch (QueryException $e) {
                        Mail::raw("Email ID = {$email->getId()} \n\CRM\n{$e->getMessage()}", function ($message) {
                            $message
                                ->to('user@friendly-solutions.com')
                                ->subject('Work Order Failed to Add');
                        });
                    }
                }

                if ($workOrderId) {
                    $workOrder = $this->workOrderRepository->find($workOrderId);

                    //assign email to created WO and mark as read
                    $email->refresh();
                    $email->work_order_id = $workOrderId;
                    $email->is_read = 1;
                    $email->subject = str_replace('{{'.$workOrderId.'}}', '', $email->getSubject());
                    $email->save();

                    if (config('email.create_thread') && !empty($email->getEmailThreadId())) {
                        /** @var EmailThreadWorkOrderRepository $emailThreadWorkOrderRepository */
                        $emailThreadWorkOrderRepository = app(EmailThreadWorkOrderRepository::class);
                        $emailThreadWorkOrderRepository->attach($email->getEmailThreadId(), $workOrderId);
                    }

                    $hotTypeId = 0;
                    $hotWorkOrderTypeId = getTypeIdByKey('task.hot');
                    if (stripos($email->getSubject(), 'invoice') !== false) {
                        $hotWorkOrderTypeId = getTypeIdByKey('task.not_hot');
                    }

                    /** @var CalendarEventRepository $calendarEventRepository */
                    $calendarEventRepository = app(CalendarEventRepository::class);
                    $calendarEventRepository->forceCreate([
                        'creator_person_id' => null,
                        'person_id'         => 0,
                        'assigned_to'       => 0,
                        'type'              => 'task',
                        'type_id'           => $hotWorkOrderTypeId,
                        'hot_type_id'       => $hotTypeId,
                        'topic'             => "WO# {$workOrder->getWorkOrderNumber()} updated.",
                        'description'       => "New email attached to WO# {$workOrder->getWorkOrderNumber()}.",
                        'tablename'         => 'work_order',
                        'record_id'         => $workOrder->getId(),
                        'time_start'        => $email->getDate()
                    ]);
                }
            } else {
//                echo "No company match\n";
            }
        } catch (\Exception $e) {
            Log::error('Cannot create work order - '.$e->getMessage());
        }
    }

    /**
     * Create work order by mobile
     *
     * @param  WorkOrderMobileStoreRequest  $workOrderMobileStoreRequest
     *
     * @return WorkOrder
     * @throws ApiErrorException
     */
    public function createWorkOrderByMobile(WorkOrderMobileStoreRequest $workOrderMobileStoreRequest)
    {
        $personId = Auth::user()->getPersonId();

        /** @var LinkPersonCompanyRepository $linkPersonCompanyRepository */
        $linkPersonCompanyRepository = app(LinkPersonCompanyRepository::class);

        /** @var Person $company */
        $company = $linkPersonCompanyRepository->getCompany($personId);
        if ($company) {
            /** @var AddressRepository $addressRepository */
            $addressRepository = app(AddressRepository::class);

            $addressId = $addressRepository->getCompanyDefaultAddress($company->getId());

            $expectedCompletionDate = Carbon::now()->addDays(2)->format('Y-m-d');
            $viaTypeId = getTypeIdByKey('via.mobile');

            $body = htmlspecialchars(trim(strip_tags($workOrderMobileStoreRequest->description)));

            $workOrderData = [
                'work_order_number'         => 'tmp',
                'company_person_id'         => $company->getId(),
                'billing_company_person_id' => $company->getId(),
                'shop_address_id'           => $addressId,
                'fin_loc'                   => '0',
                'received_date'             => Carbon::now()->format('Y-m-d'),
                'expected_completion_date'  => $expectedCompletionDate,
                'via_type_id'               => $viaTypeId,
                'completion_code'           => '0',
                'not_to_exceed'             => 0,
                'created_date'              => now(),
                'trade_type_id'             => 0,
                'description'               => $body,
                'requested_by'              => getPersonName($personId),
                'wo_type_id'                => $workOrderMobileStoreRequest->work_order_type_id,
                'crm_priority_type_id'      => $workOrderMobileStoreRequest->crm_priority_type_id,
                'creator_person_id'         => $personId,
                'requested_by_person_id'    => $personId,
            ];
            
            if(isCrmUser('fs')) {
                $workOrderData['subject'] = $this->getSubjectFromDescription($workOrderMobileStoreRequest->description);
            }
            
            /** @var WorkOrder $workOrder */
            $workOrder = WorkOrder::forceCreate($workOrderData);

            $workOrderId = $workOrder->getId();

            $workOrder->work_order_number = $workOrderId;
            $workOrder->save();

            return $workOrder;
        } else {
            throw new ApiErrorException("The user is not assigned to any company.", 422);
        }
    }

    /**
     * @return string[]
     */
    public function availableTabs()
    {
        $tabs = [
            "work-order-activity",
            "work-order-client-invoices",
            "work-order-client-ivr",
            "work-order-client-tax-breakdown",
            "work-order-client-time-sheets",
            "work-order-customer-info",
            "work-order-files",
            "work-order-invoices",
            "work-order-purchase-orders",
            "work-order-quotes",
            "work-order-surveys",
            "work-order-view-base",
            "work-order-view-extensions",
            "work-order-view-locations",
            "work-order-view-tasks",
            "work-order-view-tech-vendor-details",
            "work-order-view-tech-vendor-summary",
            "work-order-view-time-sheets",
            // "work-order-assets"
            // "work-order-instructions",
            // "work-order-opening-hours",
            // "work-order-request",
        ];

        if (config('app.crm_user') === 'fs') {
            $tabs = [
                "work-order-view-base",
                "work-order-view-tech-vendor-details",
                "work-order-activity",
//                "work-order-view-tasks",
                "work-order-view-time-sheets",
                
                "work-order-view-related-kb",
                "work-order-view-extensions",                
                
                //"work-order-related-devices",
                //"work-order-view-tech-vendor-summary",
                //"work-order-view-locations",

                "work-order-user-activity-list",
                "work-order-files",
                "work-order-invoices"
            ];
        }
        
        return $tabs;
    }

    public function getAvailableFieldsForWorkOrder(int $id)
    {
        $fields = [
            "acknowledged"             => 1,
            "actual_completion_date"   => 1,
            "authorization_code"       => 1,
            "billing_company"          => 1,
            "billing_status"           => 1,
            "category"                 => 1,
            "client_cause"             => 1,
            "client_remedy"            => 1,
            "client_status"            => 1,
            "company"                  => 1,
            "completion_code"          => 1,
            "crm_priority"             => 1,
            "customer_setting_id"      => 1,
            "equipment_needed"         => 1,
            "equipment_needed_text"    => 1,
            "estimated_time"           => 1,
            "expected_completion_date" => 1,
            "fac_supv"                 => 1,
            "invoice_number"           => 1,
            "invoice_status"           => 1,
            "not_to_exceed"            => 1,
            "opening_hours"            => 1,
            "owner"                    => 1,
            "parts_status"             => 1,
            "phone"                    => 1,
            "pickup_by"                => 1,
            "priority"                 => 1,
            "project_manager"          => 1,
            "purchase_order"           => 1,
            "quote_status"             => 1,
            "received_date"            => 1,
            "region"                   => 1,
            "requested_by"             => 1,
            "sales_tax_type"           => 1,
            "sc_check_in"              => 1,
            "sc_check_out"             => 1,
            "scheduled_date"           => 1,
            "self_billing"             => 1,
            "shop_address_id"          => 1,
            "site_hours"               => 1,
            "supplier"                 => 1,
            "tech_trade_type"          => 1,
            "trade_id"                 => 1,
            "trade_type_id"            => 1,
            "via"                      => 1,
            "wo_status"                => 1,
            "wo_type"                  => 1,
            "work_order_number"        => 1,
        ];

        if (config('app.crm_user') === 'fs') {
            $fields = [
                'acknowledged'             => 1,
                'actual_completion_date'   => 1,
                'billing_company'          => 1,
                'billing_status'           => 1,
                'client_status'            => 1,
                'company'                  => 1,
                'crm_priority'             => 1,
                'customer_setting_id'      => 1,
                'estimated_time'           => 1,
                'expected_completion_date' => 1,
                'fac_supv'                 => 1,
                'invoice_number'           => 1,
                'invoice_status'           => 1,
                'not_to_exceed'            => 1,
                'phone'                    => 1,
                'pickup_by'                => 1,
                'project_manager'          => 1,
                'received_date'            => 1,
                'requested_by'             => 1,
                'shop_address_id'          => 1,
                'via'                      => 1,
                'wo_status'                => 1,
                'work_order_number'        => 1,
                'description'              => 1,
                'request'                  => 0,
                'instructions'             => 0
            ];
        }

        return $fields;
    }

    public function getDashboardStats()
    {
        $personId = getCurrentPersonId();

        $excludeTypes = [
            getTypeIdByKey('wo_status.completed'),
            getTypeIdByKey('wo_status.canceled')
        ];

        $type = request()->get('type');

        if ($type === 'work_orders_average') {
            return $this->workOrderRepository->getStatsAverageWorkOrders();
        } elseif ($type === 'work_orders_new') {
            return $this->workOrderRepository->getStatsNewWorkOrders();
        } elseif ($type === 'work_orders_open') {
            return $this->workOrderRepository->getStatsOpenWorkOrders($excludeTypes);
        } else {
            $openForAll = $this->workOrderRepository->getOpenForAll($excludeTypes);
            $openForPerson = $this->workOrderRepository->getOpenForPerson($excludeTypes, $personId);
            $ecdForAll = $this->workOrderRepository->getEcdForAll($excludeTypes);
            $ecdForPerson = $this->workOrderRepository->getEcdForPerson($excludeTypes, $personId);

            return [
                [
                    'label' => 'Open Age - Everyone',
                    'value' => $openForAll->average ?? 0,
                ],
                [
                    'label' => 'Open Age - Me',
                    'value' => $openForPerson->average ?? 0,
                ],
                [
                    'label' => 'Open - All',
                    'value' => $openForAll->total ?? 0,
                ],
                [
                    'label' => 'Open - Me',
                    'value' => $openForPerson->total ?? 0,
                ],
                [
                    'label' => 'ECD - All',
                    'value' => $ecdForAll->total ?? 0,
                ],
                [
                    'label' => 'ECD - Me',
                    'value' => $ecdForPerson->total ?? 0,
                ],
            ];
        }
    }

    public function getAssignedMeetings()
    {
        $personId = request()->get('person_id', getCurrentPersonId());

        /** @var CalendarEventRepository $calendarEventRepository */
        $calendarEventRepository = app(CalendarEventRepository::class);

        return $calendarEventRepository->getAssignedEvents($personId, 'meeting');
    }

    public function getAssignedTasks()
    {
        $personId = request()->get('person_id', getCurrentPersonId());

        /** @var CalendarEventRepository $calendarEventRepository */
        $calendarEventRepository = app(CalendarEventRepository::class);

        return $calendarEventRepository->getAssignedEvents($personId, 'task');
    }

    public function getTechnicianSummary(TechnicianSummaryRequest $technicianSummaryRequest)
    {
        /** @var PersonDataRepository $personDataRepository */
        $personDataRepository = app(PersonDataRepository::class);

        if ($technicianSummaryRequest->get('tech_id')) {
            $techId = $technicianSummaryRequest->get('tech_id');
            $personId = $personDataRepository->getPersonIdByEmployeeId($technicianSummaryRequest->get('tech_id'));
        } else {
            $techId = $personDataRepository->getEmployeeIdByPersonId($technicianSummaryRequest->get('person_id'));
            $personId = $technicianSummaryRequest->get('person_id');
        }

        $result = [
            'technician_name' => getPersonName($personId).' ('.$personId.')',
            'tech_id'         => $techId,
            'person_id'       => $personId,
            'data'            => []
        ];

        $workOrderStats = $this->workOrderRepository->getStatsOpenWorkOrdersByPersonId($personId);
        foreach ($workOrderStats as $key => $value) {
            $result['data'][$key] = $value;
        }

        return $result;
    }

    public function statusHistory($workOrderId)
    {
        /** @var LinkPersonWoRepository $linkPersonWoRepository */
        $linkPersonWoRepository = app(LinkPersonWoRepository::class);

        /** @var WorkOrder $workOrder */
        $workOrder = $this->workOrderRepository->find($workOrderId);

        $woCompletedTypeId = getTypeIdByKey('wo_status.completed');
        $woCanceledTypeId = getTypeIdByKey('wo_status.canceled');

        $vendorInProgressTypeId = getTypeIdByKey('wo_vendor_status.in_progress');
        $vendorInConfirmedTypeId = getTypeIdByKey('wo_vendor_status.confirmed');


        $workOrderClosed = in_array($workOrder->wo_status_type_id, [$woCompletedTypeId, $woCanceledTypeId]);

        $assignedVendors = $linkPersonWoRepository->getAssignedVendors($workOrderId);
        $isAssigned = count($assignedVendors) > 0;

        $inProgress = false;
        foreach ($assignedVendors as $vendor) {
            if (in_array($vendor->status_type_id, [$vendorInConfirmedTypeId, $vendorInProgressTypeId])) {
                $inProgress = $vendor;

                break;
            }
        }

        $statuses = [
            [
                'status_name' => 'Issue created',
                'date'        => $workOrder->getCreatedAt(),
                'is_active'   => true
            ],
            [
                'status_name' => 'Technician assigned',
                'date'        => $isAssigned ? $assignedVendors[0]->getCreatedAt() : null,
                'is_active'   => $isAssigned
            ],
            [
                'status_name' => 'In progress',
                'date'        => $inProgress ? $inProgress->getCreatedAt() : null,
                'is_active'   => (bool)$inProgress,
                'technician'  => $inProgress ? getPersonName($inProgress->person_id) : null
            ],
            [
                'status_name' => $workOrder->wo_status_type_id === $woCanceledTypeId ? 'Canceled' : 'Completed',
                'date'        => $workOrderClosed ? ($workOrder->completed_date ?? $workOrder->getUpdatedAt()) : null,
                'is_active'   => $workOrderClosed
            ],
        ];

        $isActive = false;
        for ($i = count($statuses) - 1; $i >= 0; --$i) {
            if ($isActive) {
                $statuses[$i]['is_active'] = false;
            } elseif ($statuses[$i]['is_active']) {
                $isActive = true;
            }
        }

        return $statuses;
    }

    /**
     * Take the first few words of the description
     * 
     * @param $description
     *
     * @return string
     */
    public function getSubjectFromDescription($description)
    {
        $description = htmlspecialchars(trim(strip_tags($description)));
        $description = explode(' ', $description);
        
        return implode(' ', array_slice($description, 0, 6));
    }
}
