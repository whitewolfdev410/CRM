<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Exceptions\NotImplementedException;
use App\Core\Old\DateConverter;
use App\Core\Trans;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use App\Modules\TimeSheet\Models\TimeSheet;
use App\Modules\TimeSheet\Models\TimeSheetReason;
use App\Modules\TimeSheet\Repositories\TimeSheetReasonRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Models\WorkOrderStatus;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use App\Services\MobileItemService;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use stdClass;

class WorkOrderMobileService extends MobileItemService
{
    /**
     * @var WorkOrderRepository
     */
    protected $woRepo;

    /**
     * @var TimeSheetRepository
     */
    protected $tsr;

    /**
     * Work Order data
     *
     * @var WorkOrder
     */
    protected $wo;

    /**
     * Whether completion code is required
     *
     * @var bool
     */
    protected $completionCodeRequired = false;

    /**
     * Ongoing timer
     *
     * @var Timesheet|null
     */
    protected $ongoingTimer = null;

    /**
     * Ongoing LpWo Timer
     *
     * @var Timesheet|null
     */
    protected $ongoingLpWoTimer = null;

    /**
     * TimeSheet reason settings
     *
     * @var array|null
     */
    protected $reasonSettings = null;

    /**
     * @var
     */
    protected $allowGhostLink;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param WorkOrderRepository $woRepo
     * @param Trans $trans
     * @param TimeSheetRepository $tsr
     */
    public function __construct(
        Container $app,
        WorkOrderRepository $woRepo,
        Trans $trans,
        TimeSheetRepository $tsr
    ) {
        $type = 'work_order';
        if ($this->isDisplayingAssigned($app)) {
            $type = 'work_order2';
        }

        // set allow ghost link
        $this->setAllowGhostLink();

        parent::__construct($app, $trans, $type);
        $this->woRepo = $woRepo;
        $this->tsr = $tsr;
    }

    /**
     * Set ongoing timer
     *
     * @param $timer
     */
    public function setOngoingTimer($timer)
    {
        $this->ongoingTimer = $timer;
    }

    /**
     * Set ongoing link_person_wo timer
     *
     * @param $timer
     */
    public function setOngoingLpWoTimer($timer)
    {
        $this->ongoingLpWoTimer = $timer;
    }

    /**
     * Set allow ghost link (from config)
     */
    protected function setAllowGhostLink()
    {
        $this->allowGhostLink =
            (int) config('crm_settings.allow_ghost_link', 0);
    }

    /**
     * Get ongoing timer
     *
     * @return TimeSheet
     */
    public function getOngoingTimer()
    {
        return $this->tsr->getOngoingTimeSheet();
    }

    /**
     * Get ongoing link_person_wo timer
     *
     * @param int $personId
     * @return TimeSheet
     */
    public function getOngoingLpWoTimer($personId)
    {
        return $this->tsr->getOngoingTimeSheet(
            'link_person_wo',
            $personId,
            null,
            'created_date'
        );
    }

    /**
     * Get mobile Work Order
     *
     * @param int $id
     * @param string $type
     * @param string $userTimeZone
     *
     * @return array
     */
    public function getMobileItem($id, $type, $userTimeZone)
    {
        $ongoing = $this->getOngoingTimer();
        $this->setOngoingTimer($ongoing);
        $ongoingId = ($ongoing) ? $ongoing->getId() : 0;

        $item = $this->woRepo->findMobile($id, $type, $ongoingId);
        list($item, $info) = $this->prepareData(
            $item,
            getCurrentPersonId(),
            $type,
            $userTimeZone
        );

        // add info to item + ongoing id and assign it to wo property
        $item->info = $info;
        $item->ongoingTimeSheetId = $ongoingId;
        $this->wo = $item;

        // extra data that will be used in view but NOT set to $this->wo)
        $item->current_status = $this->getCurrentStatus();
        $item->is_travel_type = $this->isCurrentlyTravelling();

        // set Work Order and info data to reuse them when setting buttons

        // @todo temporary generating random description
        //$item->wo_description = generateSampleDescription();
        //$item->qb_info = generateSampleDescription();

        // create item that will be in output - we use only a piece of data
        $newItem = new stdClass();
        $newItem->reference_number = $item->getId();

        // for both work_order and work_order2 we use here same view but we could
        // separate it if needed
        $newItem->html = str_replace(
            "\n",
            '',
            trim(view(
                'mobile.work_order.details',
                ['wo' => $item, 'status' => new WorkOrderStatus()]
            )->render())
        );
        // $newItem->info = $info; it's not needed in output

        // if in parameter there's is link_person_wo_id set it as link_to
        $linkPersonWoId = $this->app->request->input('link_person_wo_id', null);
        if ($linkPersonWoId !== null) {
            $newItem->link_to = [
                'table_name' => 'link_person_wo',
                'record_id' => $linkPersonWoId,
            ];
        } else {
            $newItem->link_to = [
                'table_name' => 'work_order',
                'record_id' => $item->getId(),
            ];
        }

        $items = new Collection([$newItem]);

        $paginator = new LengthAwarePaginator($items, 1, 1, 1, [
            'path' => $this->app->request->url(),
            'query' => $this->app->request->query(),
        ]);
        $data = $paginator->toArray();
        $data['buttons'] = $this->getButtons($item);
        $data['title'] = $this->getItemTitle($item);
        $data['search_bar_enabled'] = false;

        return $data;
    }

    /**
     * Verify if current link person wo is travelling
     *
     * @return bool
     */
    public function isCurrentlyTravelling()
    {
        // It can be left as is R. K. on 15.10.2015 (CRM-413)
        if ($this->ongoingTimer && $this->isCurrentlyDoing() &&
            isset($this->reasonSettings['summary_group']) &&
            ($this->reasonSettings['summary_group'] == 'Travel')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Verify if want to display assigned work order
     *
     * @param Container $app
     *
     * @return bool
     */
    protected function isDisplayingAssigned(Container $app)
    {
        return $app->request->input('link_person_wo_id', null) !== null;
    }

    /**
     * Get work order title
     *
     * @param WorkOrder $item
     *
     * @return string
     */
    protected function getItemTitle($item)
    {
        return $this->getTitle(['work_order_number' => $item->work_order_number]);
    }

    /**
     * Verify assigned status and get valid vendor status and assigned to me
     *
     * @param int $personId
     * @param $wo
     * @param bool|true $assignedToMe
     * @return array
     */
    public function verifyAssignedStatus($personId, $wo, $assignedToMe = true)
    {
        $vendorStatus = $wo->vendor_status;

        if ($personId != $wo->person_id) {
            // check if he's assigned
            if ($this->isPersonAssigned($personId, $wo->work_order_id)) {
                $vendorStatus = $this->getWorkOrderStatusId('LOCKED');
            } else {
                $assignedToMe = false;
            }
        }

        return [$vendorStatus, $assignedToMe];
    }

    /**
     * Make any data conversions or calculations
     *
     * @param Model $item
     * @param int $personId
     * @param string $type
     * @param string $userTimeZone
     *
     * @return array
     */
    protected function prepareData(
        $item,
        $personId,
        $type,
        $userTimeZone
    ) {
        $displayMode = 'edit';
        if ($type == 'work_order') {
            $displayMode = 'view';
        }

        if ($this->allowGhostLink) {
            if ($item->is_ghost == 1 && $item->vendor_status == 'Assigned') {
                $displayMode = 'view';
            }
        }

        /** @var DateConverter $dc */
        $dc = $this->app->make(DateConverter::class);

        // converting dates to User timezone based on user timezone
        $item->expected_completion_date
            = $dc->toUser(
                $item->expected_completion_date,
                $userTimeZone,
                'd/m/y'
            );
        $item->received_date = $dc->toUser(
            $item->received_date,
            $userTimeZone,
            'd/m/y'
        );
        $item->confirmed_date = $dc->toUser(
            $item->confirmed_date,
            $userTimeZone,
            'd/m/y'
        );

        if (!isset($item->days_to_ecd)) {
            $item->days_to_ecd = 0;
        }
        $item->gps_latitude = '0.0';
        $item->gps_longitude = '0.0';
        $item->city = rtrim(ucwords(mb_strtolower($item->city)), ',');
        $item->state = mb_strtoupper($item->state);

        $item->has_signature = empty($item->has_signature) ? false : true;

        $item->vendor_status = $this->getWorkOrderValidStatus($item);

        $vendorStatusDB = $item->vendor_status;
        $item->vendor_status =
            $this->getWorkOrderStatusId($item->vendor_status);
        $item->im_assigned = true;
        $item->has_work_timer_started = false; // few line below we update it

        // if another user is displaying work order information
        list($item->vendor_status, $item->im_assigned) =
            $this->verifyAssignedStatus($personId, $item, $item->im_assigned);

        $item->ESVC_approved = ($item->special_type == '2hr_min');
        if (empty($item->days_to_ecd)) {
            $item->days_to_ecd = '0';
        }

        $isCommentRequiredToStopTimer = false;
        $tsrSettings = ['tag' => ''];

        $ongoingTimeSheet = $this->ongoingTimer;

        if ($ongoingTimeSheet) {
            $tsrSettings
                = $this->getTsrSettings($ongoingTimeSheet->getReasonTypeId());
            $this->setTsrSettings($tsrSettings);

            $isCommentRequiredToStopTimer
                = !empty($tsrSettings['comment_required']);
            $item->has_work_timer_started = ($tsrSettings['summary_group']
                == 'Work');
            if ($vendorStatusDB != 'Completed' && isset($tsrSettings['tags'])
                && ($tsrSettings['tag'] == 'work'
                    || $tsrSettings['tag'] == 'work_th'
                    || $tsrSettings['tag'] == 'work_dbl')
            ) {
                $isCommentRequiredToStopTimer = true;
            }
        }

        /** @var CustomerSettingsRepository $csr */
        $csr = $this->app->make(CustomerSettingsRepository::class);

        if ($item->customer_setting_id > 0) {
            $cs = $csr->find($item->customer_setting_id);
        } else {
            $cs = $csr->getForPerson($item->company_person_id);
        }

        $footerText = $cs ? $cs->getFooterText() : '';

        $item->instructions = str_replace(
            ['\r\n', '\r\n'],
            ["\n", "\n"],
            $footerText
        );

        $ongoingId = ($ongoingTimeSheet) ? $ongoingTimeSheet->getId() : 0;

        $ongoingLpWo = $this->getOngoingLpWoTimer($personId);

        $this->setOngoingLpWoTimer($ongoingLpWo);

        $ongoingLpWoTableId = $this->getOngoingLpWoTableId();

        $this->completionCodeRequired =
            ($cs && $cs->getRequiredCompletionCode() == 1)
                ? true : false;

        $info = [
            'vendor_status' => $item->vendor_status,
            'im_assigned' => $item->im_assigned,
            'ESVC_approved' => $item->ESVC_approved,
            'has_work_timer_started' => $item->has_work_timer_started,
            'instructions' => $item->instructions,
            'not_completed_lpwo_id' => $ongoingLpWoTableId,
            'stopped_timers' => ($ongoingTimeSheet ? false : true),
            'current_time_sheet_id' => $ongoingId,
            'current_time_sheet_is_work' => (isset($tsrSettings['tag'])
                && $tsrSettings['tag'] == 'work') ? true : false,
            'current_time_sheet_summary_group' =>
                isset($tsrSettings['summary_group'])
                    ? $tsrSettings['summary_group'] : null,
            'timer_types' => $this->getTimerTypes(),
            'comment_required' => (bool)(int)$isCommentRequiredToStopTimer,
            'completion_code_required' => $this->completionCodeRequired,
            'display_mode' => $displayMode,
        ];

        return [$item, $info];
    }

    /**
     * Get work order status to ISSUED if vendor_status is empty
     *
     * @param $wo
     *
     * @return string
     */
    public function getWorkOrderValidStatus($wo)
    {
        if (empty($wo->vendor_status)) {
            return 'ISSUED';
        }

        return $wo->vendor_status;
    }

    /**
     * Get table_id from ongoing link person wo timer
     *
     * @return int|null
     */
    protected function getOngoingLpWoTableId()
    {
        return ($this->ongoingLpWoTimer
            ? $this->ongoingLpWoTimer->getTableId() : null);
    }

    /**
     * Get TimeSheet reason types
     *
     * @return array
     */
    protected function getTimerTypes()
    {
        /** @var TimeSheetReasonRepository $tsrr */
        $tsrr = $this->app->make(TimeSheetReasonRepository::class);

        $reasons = $tsrr->getDroid();
        $data = [];
        foreach ($reasons as $reason) {
            $data[] = [
                'tag' => trim($reason->type_value),
                'label' => trim($reason->name),
            ];
        }

        return $data;
    }

    /**
     * Get Time sheet reason type settings
     *
     * @param int $reasonTypeId
     *
     * @return array
     */
    public function getTsrSettings($reasonTypeId)
    {
        /** @var TimeSheetReasonRepository $tsrr */
        $tsrr = $this->app->make(TimeSheetReasonRepository::class);

        /** @var TimeSheetReason $tsr */
        $tsr = $tsrr->findByReasonTypeId($reasonTypeId, true);

        /** @var TypeRepository $tr */
        $tr = $this->app->make(TypeRepository::class);

        if ($tsr) {
            return [
                'tag' => trim($tr->getValueById($tsr->getReasonTypeId())),
                'type_id' => $tsr->getReasonTypeId(),
                'label' => trim($tsr->getName()),
                'comment_required' => intval($tsr->getIsDescriptionRequired()),
                'assign_to_wo' => intval($tsr->getIsWorkOrderRelated()),
                'summary_group' => $tsr->getSummaryGroup(),
            ];
        }

        return false;
    }

    /**
     * Set Time sheet reason settings
     *
     * @param $tsrSettings
     */
    public function setTsrSettings($tsrSettings)
    {
        $this->reasonSettings = $tsrSettings;
    }

    /**
     * Get Work Order status id
     *
     * @param string $vendor_status
     *
     * @return int
     */
    public function getWorkOrderStatusId($vendor_status)
    {
        /* @todo not a nice solution - it's based on user input again and if someone
         * change Locked to Lock in CRM it won't work*/
        $vendor_status = strtoupper(trim($vendor_status));

        $array = [
            'LOCKED' => WorkOrderStatus::LOCKED,
            'ASSIGNED' => WorkOrderStatus::ASSIGNED,
            'ISSUED' => WorkOrderStatus::ISSUED,
            'CONFIRMED' => WorkOrderStatus::CONFIRMED,
            'IN PROGRESS' => WorkOrderStatus::IN_PROGRESS,
            'IN PROGRESS & HOLD' => WorkOrderStatus::IN_PROGRESS_AND_HOLD,
            'COMPLETED' => WorkOrderStatus::COMPLETED,
            'CANCELED' => WorkOrderStatus::CANCELED,
            'RFQ ISSUED' => WorkOrderStatus::ISSUED,
            'RFQ CONFIRMED' => WorkOrderStatus::CONFIRMED,
            'RFQ RECEIVED' => WorkOrderStatus::COMPLETED,
        ];

        return (isset($array[$vendor_status])) ? $array[$vendor_status] : WorkOrderStatus::LOCKED;
    }

    /**
     * Verify if person is assigned to work order
     *
     * @param int $personId
     * @param int $workOrderId
     *
     * @return bool
     */
    public function isPersonAssigned($personId, $workOrderId)
    {
        /** @var LinkPersonWoRepository $lpwo */
        $lpwo = $this->app->make(LinkPersonWoRepository::class);

        return $lpwo->isPersonAssignedForWo($workOrderId, $personId);
    }

    /**
     * Set params for gotoOpenLocation action
     *
     * @param Model $item
     * @param array $button
     *
     * @return array
     */
    protected function setLocationParams($item, array $button)
    {
        $button['action']['params'] = array_merge($button['action']['params'], [
            ['city' => $item->city],
            ['state' => $item->state],
            ['zip_code' => $item->zip_code],
            ['address' => $item->address],
        ]);

        return $button;
    }

    /**
     * Set params for complete work order action
     *
     * @param Model $item
     * @param array $button
     *
     * @return array
     */
    protected function setCompleteWorkOrderParams($item, array $button)
    {
        // if there is completion_code_required param
        if (isset($button['action']['params'])) {
            // Merge button params
            $button['action']['params']
                = array_merge($button['action']['params'], [
                ['record_id' => $item->id],
                ['link_person_wo_id' => $item->link_person_wo_id],
                ['completion_code_required' => (int)$this->completionCodeRequired],
                ]);
        }

        return $button;
    }

    /**
     * Set link_person_wo table name and record_id button params if link_person_wo_id is set
     *
     * @param Model $item
     * @param array $button
     *
     * @return array
     */
    protected function setLinkPersonWoIdParams($item, array $button)
    {
        // if there are params
        if (isset($button['action']['params'])) {
            // if there is link_person_wo_id set
            if ($this->wo->link_person_wo_id) {
                // Merge button params
                $button['action']['params']
                    = array_merge($button['action']['params'], [
                    ['table_name' => 'link_person_wo'],
                    ['record_id' => $item->link_person_wo_id]
                    ]);
            }
        }

        return $button;
    }

    /**
     * Set params for show store photos work order action
     *
     * @param Model $item
     * @param array $button
     *
     * @return array
     */
    protected function setStorePhotosParams($item, array $button)
    {
        // if shop_address_id and button params are set
        if (isset($button['action']['params']) && $item->shop_address_id) {
            // Merge button params
            $button['action']['params']
                = array_merge($button['action']['params'], [
                ['record_id' => $item->shop_address_id],
                ]);
        }

        return $button;
    }

    /**
     * Process all buttons (for DEV purposes only)
     *
     * @param Object $item
     * @param string $index
     * @param array $button
     * @return array
     */
    protected function processAllButtons($item, $index, array $button)
    {
        // @todo this is only testing method - shows all buttons for dev purposes
        switch ($index) {
            case 'open_location':
                $button = $this->setLocationParams($item, $button);
                break;
            case 'get_signature':
                $button = $this->setSignatureParams($button);
                break;
            case 'confirm_work_order':
                $button = $this->setLinkPersonWoId($button);
                break;
            case 'complete_work_order':
                $button = $this->setCompleteWorkOrderParams($item, $button);
                break;
            case 'show_store_photos':
                $button = $this->setStorePhotosParams($item, $button);
                break;
            default:
                $button = $this->showButton($button);
                break;
        }

        return $button;
    }

    /**
     * Processes single button - it launches any extra actions for button (if
     * needed) and might set button as inactive or not enabled
     *
     * @param Object $item
     * @param string $index
     * @param array $button
     *
     * @return array
     */
    protected function processButton($item, $index, array $button)
    {
        // @todo - all_buttons parameter only for testing
        $allButtons = $this->app->request->input('all_buttons', 0);
        if ($allButtons) {
            return $this->processAllButtons($item, $index, $button);
        }

        switch ($index) {
            case 'open_location':
                $button = $this->setLocationParams($item, $button);
                // not listed in XLS so hide it at the moment
                $button = $this->hideButton($button);
                break;
            case 'timer_start':
                $button = $this->setTimerStart($button);
                break;
            case 'timer_stop':
                $button = $this->setTimerStop($button);
                break;
            case 'confirm_work_order':
                $button = $this->setLinkPersonWoId($button);
                $button = $this->setConfirmWorkOrder($button);
                break;
            case 'complete_work_order':
                $button = $this->setCompleteWorkOrderParams($item, $button);
                $button = $this->setCompleteWorkOrder($button);
                break;
            case 'get_signature':
                $button = $this->setGetSignature($button);
                $button = $this->setSignatureParams($button);
                break;
            case 'add_materials':
                if ($this->allowGhostLink && $item->is_ghost == 1 && $item->vendor_status == WorkOrderStatus::ASSIGNED) {
                    $button = $this->hideButton($button);
                } else {
                    $button = $this->showButton($button);
                }
                break;
            case 'show_store_photos':
                $button = $this->setStorePhotosParams($item, $button);
                $button = $this->showButton($button);
                break;
            case 'show_store_videos':
                $button = $this->showButton($button);
                break;
            case 'show_activities':
                if ($this->allowGhostLink && $item->is_ghost == 1 && $item->vendor_status == WorkOrderStatus::ASSIGNED) {
                    $button = $this->hideButton($button);
                } else {
                    $button = $this->showButton($button);
                }
                break;
            case 'location_history':
                $button = $this->showButton($button);
                break;
            case 'location_assets':
                $button = $this->hideButton($button);
                break;
            case 'upload_photos':
                if ($this->allowGhostLink && $item->is_ghost == 1 && $item->vendor_status == WorkOrderStatus::ASSIGNED) {
                    $button = $this->hideButton($button);
                } else {
                    $button = $this->showButton($button);
                }
                break;
            case 'upload_videos':
                if ($this->allowGhostLink && $item->is_ghost == 1 && $item->vendor_status == WorkOrderStatus::ASSIGNED) {
                    $button = $this->hideButton($button);
                } else {
                    $button = $this->showButton($button);
                }
                break;
            case 'add_activity':
                if ($this->allowGhostLink && $item->is_ghost == 1 && $item->vendor_status == WorkOrderStatus::ASSIGNED) {
                    $button = $this->hideButton($button);
                } else {
                    $button = $this->showButton($button);
                }
                break;
            default:
                $button = $this->hideButton($button);
                break;
        }

        return $button;
    }

    /**
     * Set signature params if there is ongoing time sheet
     *
     * @param array $button
     *
     * @return array
     */
    protected function setSignatureParams(array $button)
    {
        // if there is ongoing time sheet  add extra data to signature
        if ($this->wo->ongoingTimeSheetId) {
            $button['action']['params'] =
                array_merge($button['action']['params'], [
                    ['table_name' => 'time_sheet', ],
                    ['record_id' => $this->wo->ongoingTimeSheetId, ],
                    ['type_id' => getTypeIdByKey('signature.work_order')],
                ]);
        }

        return $button;
    }

    /**
     * Add link_person_wo_id to button if link_person_wo_id is set
     *
     * @param array $button
     *
     * @return array
     */
    protected function setLinkPersonWoId(array $button)
    {
        // if there is link_person_wo_id set
        if ($this->wo->link_person_wo_id) {
            // changing method with {link_person_wo_id} to link_person_wo_id
            $button['action']['method']
                = str_replace(
                    '{link_person_wo_id}',
                    $this->wo->link_person_wo_id,
                    $button['action']['method']
                );
        }

        return $button;
    }

    /**
     * Hides button
     *
     * @param array $button
     *
     * @return array
     */
    protected function hideButton(array $button)
    {
        $button['enabled'] = false;

        return $button;
    }

    /**
     * Shows buttons and set active set to given (default true)
     *
     * @param array $button
     * @param bool $active
     *
     * @return array
     */
    protected function showButton(array $button, $active = true)
    {
        $button['enabled'] = true;
        $button['active'] = $active;

        return $button;
    }

    /**
     * Set timer start button
     *
     * @param array $button
     *
     * @return array
     */
    protected function setTimerStart(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $enabled = true;
            $active = false;
        } else {
            $currentStatus = $this->getCurrentStatus();
            switch ($currentStatus) {
                case WorkOrderStatus::CONFIRMED:
                    if ($this->canStartTimer()) {
                        $active = true;
                    }
                    break;
                case WorkOrderStatus::IN_PROGRESS_AND_HOLD:
                    if ($this->canStartTimer()) {
                        $active = true;
                    }
                    break;
                case WorkOrderStatus::COMPLETED:
                    if ($this->canStartTimer() || !$this->isCurrentlyDoing()) {
                        $active = true;
                    }
                    break;
            }
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Set timer stop button
     *
     * @param array $button
     *
     * @return array
     */
    protected function setTimerStop(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $enabled = true;
            $active = false;
        } else {
            $currentStatus = $this->getCurrentStatus();
            switch ($currentStatus) {
                case WorkOrderStatus::IN_PROGRESS:
                    $active = true;
                    break;
                case WorkOrderStatus::COMPLETED:
                    if (!$this->canStartTimer() && $this->isCurrentlyDoing()) {
                        $active = true;
                    }
                    break;
            }
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Set confirm work order button
     *
     * @param array $button
     *
     * @return array
     */
    protected function setConfirmWorkOrder(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $enabled = false;
        } else {
            $currentStatus = $this->getCurrentStatus();
            switch ($currentStatus) {
                case WorkOrderStatus::ISSUED:
                    $active = true;
                    break;
            }
        }

        // extra logic after standard checking statuses
        $copyCurrentStatus =
            $this->getActualStatus(); // @todo is it the correct way?
        $currentStatus = $this->getCurrentStatus();

        if ($currentStatus == WorkOrderStatus::LOCKED &&
            $copyCurrentStatus == WorkOrderStatus::ISSUED
        ) {
            $active = true;
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Set complete work order button
     *
     * @param array $button
     *
     * @return array
     */
    public function setCompleteWorkOrder(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $enabled = false;
        } else {
            $currentStatus = $this->getCurrentStatus();
            switch ($currentStatus) {
                case WorkOrderStatus::CONFIRMED:
                    if ($this->canStartTimer()) {
                        $active = true;
                    }
                    break;
                case WorkOrderStatus::IN_PROGRESS:
                    $active = true;
                    break;
                case WorkOrderStatus::IN_PROGRESS_AND_HOLD:
                    if ($this->canStartTimer()) {
                        $active = true;
                    }
                    break;
            }
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Set assign me to work order button
     *
     * @param array $button
     *
     * @return array
     */
    public function setAssignMeToWorkOrder(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $active = true;
        } else {
            $currentStatus = $this->getCurrentStatus();
            switch ($currentStatus) {
                case WorkOrderStatus::LOCKED:
                    $enabled = false;
                    break;
                case WorkOrderStatus::ISSUED:
                    $enabled = false;
                    break;
                case WorkOrderStatus::CONFIRMED:
                    $enabled = false;
                    break;
                case WorkOrderStatus::IN_PROGRESS:
                    $enabled = false;
                    break;
                case WorkOrderStatus::IN_PROGRESS_AND_HOLD:
                    $enabled = false;
                    break;
                case WorkOrderStatus::COMPLETED:
                    $enabled = false;
                    break;
                case WorkOrderStatus::CANCELED:
                    $enabled = false;
                    break;
            }
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Set get as signature button
     *
     * @param array $button
     *
     * @return array
     */
    protected function setGetSignature(array $button)
    {
        $enabled = true;
        $active = false;

        if (!$this->imAssigned()) {
            $enabled = false;
        } else {
            $currentStatus = $this->getCurrentStatus();

            switch ($currentStatus) {
                case WorkOrderStatus::IN_PROGRESS:
                    if (!$this->hasSignature()) {
                        $active = true;
                    }
                    break;
                case WorkOrderStatus::IN_PROGRESS_AND_HOLD:
                    if (!$this->canStartTimer() && !$this->hasSignature()) {
                        $active = true;
                    }
                    break;
                case WorkOrderStatus::COMPLETED:
                    if (!$this->canStartTimer() && $this->isCurrentlyDoing() &&
                        !$this->hasSignature()
                    ) {
                        $active = true;
                    }
                    break;
            }
        }

        // if there is no ongoing time sheet make sure this button is inactive
        if (!$this->wo->ongoingTimeSheetId) {
            $active = false;
        }

        $button['active'] = $active;
        $button['enabled'] = $enabled;

        return $button;
    }

    /**
     * Verify whether current user is assigned to this work order
     *
     * @return bool
     */
    protected function imAssigned()
    {
        return $this->wo->im_assigned;
    }

    /**
     * Verify if user can start current timer
     *
     * @return bool
     */
    protected function canStartTimer()
    {
        return ($this->getOngoingLpWoTableId() == 0);
    }

    /**
     * Verify if there is file signature for current time sheet
     *
     * @return bool
     */
    protected function hasSignature()
    {
        return $this->wo->has_signature;
    }

    /**
     * Verify if user is doing at the moment job related to this work order
     *
     * @return bool
     */
    protected function isCurrentlyDoing()
    {
        return $this->getOngoingLpWoTableId() == $this->wo->link_person_wo_id;
    }

    /**
     * Verify if work order has been put on hold
     *
     * @return bool
     * @throws NotImplementedException
     */
    protected function isPutOnHold()
    {
        // this is not implemented so just in case throw exception
        /** @var NotImplementedException $exception */
        $exception = $this->app->make(NotImplementedException::class);
        $exception->setData([
            'class' => __CLASS__,
            'line' => __LINE__,
        ]);
        throw $exception;

        // @todo there is no wo_put_on_hold !!!!
        return $this->wo->wo_put_on_hold != null;
    }

    /**
     * Get vendor status for current work order
     *
     * @return int
     */
    protected function getVendorStatus()
    {
        return $this->wo->vendor_status;
    }

    /**
     * Verify if there is any ongoing timer for current person
     *
     * @return bool
     */
    protected function areAllTimersStopped()
    {
        return ($this->ongoingTimer ? false : true);
    }

    /**
     * Get Work Order actual status (vendor status that might be modified)
     *
     * @return int
     */
    protected function getActualStatus()
    {
        $actualStatus = $this->wo->vendor_status;
        if ($this->isCurrentlyDoing() &&
            ($actualStatus != WorkOrderStatus::COMPLETED)
        ) {
            $actualStatus = WorkOrderStatus::IN_PROGRESS;
        }

        return $actualStatus;
    }

    /**
     * Get current work order status (actual status that might be modified)
     *
     * @return int
     */
    public function getCurrentStatus()
    {
        $currentStatus = $this->getActualStatus();

        $isAnotherTimerRunning = (
            !$this->areAllTimersStopped()
            && !$this->isCurrentlyDoing()
            && $this->canStartTimer()
        );

        if ($isAnotherTimerRunning) {
            $currentStatus = WorkOrderStatus::LOCKED;
        }

        return $currentStatus;
    }

    /**
     * Set work order
     *
     * @param $wo
     */
    public function setWorkOrder($wo)
    {
        $this->wo = $wo;
    }

    /**
     * Get company person id for work orders
     *
     * @param $workOrderIds
     *
     * @return array
     */
    public static function getCompanyPersonIdByWorkOrderIds($workOrderIds)
    {
        if (!$workOrderIds) {
            return [];
        }
        
        return WorkOrder::whereIn('work_order_id', $workOrderIds)
            ->pluck('company_person_id', 'work_order_id')
            ->all();
    }
}
