<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Crm;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Contact\Models\Contact;
use App\Modules\Contact\Repositories\ContactRepository;
use App\Modules\Email\Services\EmailSenderService;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\PushNotification\Services\PushNotificationAdderService;
use App\Modules\TimeSheet\Services\TimeSheetService;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;

class LinkPersonWoRecordService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * Initialize class
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Launch actions after new link person WO record is created
     *
     * @param LinkPersonWo $lpWo
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function inserted(LinkPersonWo $lpWo)
    {
        // @todo data exchange partners #3659

        $this->addPushNotification($lpWo);

        /** @var Crm $crm */
        $crm = $this->app->make('crm');

        if ($crm->is('fs')
            && $crm->get('Notification.employee_assigned') == 1
        ) {
            $creatorId = $lpWo->getCreatorPersonId();
            $personId = $lpWo->getPersonId();

            $person = $this->getPerson($personId);

            if ($creatorId != $personId && $person
                && $person->getKind() == 'person'
                && $person->getTypeId() == $this->getTypeId('person.employee')
            ) {
                $contact = $this->getEmailContact($person);

                if ($contact !== false) {
                    $wo = $this->getWorkOrder($lpWo->getWorkOrderId());
                    $creatorName = $this->getPersonName($creatorId);

                    $this->sendNotificationEmail(
                        $contact->getValue(),
                        $crm->get('notification_from_email'),
                        $wo,
                        $creatorName
                    );
                }
            }
        }
    }

    /**
     * Add push notification when person has been assigned to work order
     *
     * @param LinkPersonWo $lpWo
     */
    protected function addPushNotification(LinkPersonWo $lpWo)
    {
        /** @var PushNotificationAdderService $service */
        $service = $this->app->make(PushNotificationAdderService::class);
        $service->addNewWorkOrder($lpWo);
    }

    /**
     * Send notification e-mail
     *
     * @param string    $to
     * @param string    $from
     * @param WorkOrder $wo
     * @param string    $creatorName
     */
    protected function sendNotificationEmail(
        $to,
        $from,
        WorkOrder $wo,
        $creatorName
    ) {
        $mailService = $this->app->make(EmailSenderService::class);
        $mailService->sendHtml(
            $to,
            $from,
            'You were assigned to WO#' . $wo->getWorkOrderNumber(),
            'emails.notifications.employee_assigned',
            ['wo' => $wo, 'creatorName' => $creatorName]
        );
    }

    /**
     * Get person name
     *
     * @param int $personId
     *
     * @return string|null
     */
    protected function getPersonName($personId)
    {
        $personRepo = $this->app->make(PersonRepository::class);
        $person = $personRepo->getPersonData($personId, 'person_name');
        if ($person) {
            return $person->person_name;
        }

        return;
    }

    /**
     * Get work order
     *
     * @param int $workOrderId
     *
     * @return WorkOrder
     */
    protected function getWorkOrder($workOrderId)
    {
        $woRepo = $this->app->make(WorkOrderRepository::class);

        /** @var WorkOrder $wo */
        $wo = $woRepo->getBasicForDisplay($workOrderId);

        $typeRepo = $this->app->make(TypeRepository::class);
        $wo->priority = $typeRepo->getValueById($wo->getCrmPriorityTypeId());

        return $wo;
    }

    /**
     * Get email contact for given person
     *
     * @param Person $person
     *
     * @return Contact|bool
     *
     * @throws \App\Core\Exceptions\NotImplementedException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function getEmailContact(Person $person)
    {
        $contactRepo = $this->app->make(ContactRepository::class);

        return $contactRepo
            ->getDefaultForPerson($person, $this->getTypeId('contact.email'));
    }

    /**
     * Get type id by key
     *
     * @param string $key
     *
     * @return int
     */
    protected function getTypeId($key)
    {
        $typeRepo = $this->app->make(TypeRepository::class);

        return $typeRepo->getIdByKey($key);
    }

    /**
     * Find person with given id
     *
     * @param int $personId
     *
     * @return Person
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function getPerson($personId)
    {
        $personRepo = $this->app->make(PersonRepository::class);

        return $personRepo->basicFind($personId);
    }

    /**
     * Launch actions after link person WO record is updated
     *
     * @param LinkPersonWo $lpWo
     */
    public function updated(LinkPersonWo $lpWo)
    {
        if ($lpWo->isDirty('special_type')) {
            $workReasonId = $this->getTypeId('time_sheet_reason.work');
            $personId = $lpWo->getPersonId();
            $ids = $this->getLpWoIds($lpWo->getWorkOrderId(), $personId);

            //min 2hr - update first work time sheet rounded duration
            $tss = $this->app->make(TimeSheetService::class);
            $timeSheet = $tss->getFirstLpWoWithReason($workReasonId, $ids);
            if ($timeSheet) {
                $tss->updateDurationTimes($timeSheet);
            }

            //lunch deduction - update lunch deduction for all
            $dates = $tss->listDistinctLpWoEntryDates($ids, $personId);
            $curDate = date('Y-m-d');
            foreach ($dates as $date) {
                if ($date != $curDate) {
                    $tss->updateLunchDeduction($personId, $date);
                }
            }
        }
        if ($lpWo->isDirty('send_past_due_notice')) {
            /** @var ActivityRepository $actRepo */
            $actRepo = $this->app->make(ActivityRepository::class);

            $note = 'Letter series ' . ($lpWo->getSendPastDueNotice() > 0
                    ? 'enabled' : 'disabled') . ' for "'
                . $this->getPersonName($lpWo->getPersonId()) . '"';

            /* @todo - should really getCurrentPersonId() be used here
             * and not $lpWo->getPersonId()  ?
             */
            $actRepo->add('work_order', $lpWo->getWorkOrderId(), $note, '', getCurrentPersonId());
        }
    }

    /**
     * Return pin for link person wo
     *
     * @param  integer $linkPersonWoId
     *
     * @return string
     */
    public function getPin($linkPersonWoId)
    {
        $numbers = $linkPersonWoId * 37;
        $num = 0;
        $temp = (string)$numbers;
        $length = strlen($numbers);

        for ($i = 0; $i < $length; $i++) {
            $num += $temp[$i];
        }

        return $numbers . ($num % 10);
    }

    /**
     * Get LpWO ids for given work order and person
     *
     * @param int $workOrderId
     * @param int $personId
     *
     * @return array
     */
    protected function getLpWoIds($workOrderId, $personId)
    {
        $lpr = $this->app->make(LinkPersonWoRepository::class);

        return $lpr->listDistinctForWorkOrderAndPerson($workOrderId, $personId);
    }
}
