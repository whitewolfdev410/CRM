<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\Crm;
use App\Core\Exceptions\ObjectNotFoundException;
use App\Core\Trans;
use App\Modules\Activity\Models\Activity;
use App\Modules\Activity\Repositories\ActivityRepository;
use App\Modules\Contact\Repositories\ContactRepository;
use App\Modules\Email\Exceptions\NoEmailForCurrentUserException;
use App\Modules\Email\Models\Email;
use App\Modules\Email\Repositories\EmailRepository;
use App\Modules\Email\Services\EmailSenderService;
use App\Modules\File\Services\FileService;
use App\Modules\Notification\Repositories\NotificationRepository;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\WorkOrder\Exceptions\LpWoMissingWorkOrderException;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Services\PdfPrinter\FaxService;
use App\Modules\WorkOrder\Services\PdfPrinter\WorkOrderPdfDataService;
use App\Modules\WorkOrder\Services\PdfPrinter\WorkOrderPdfPrinter;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LinkPersonWoPrintService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var LinkPersonWoRepository
     */
    protected $lpWoRepo;

    /**
     * LinkPersonWoPrintService constructor.
     *
     * @param Container $app
     * @param LinkPersonWoRepository $lpWoRepo
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
    }

    /**
     * Get data necessary for action to choose files and actions
     *
     * @param int $lpWoId
     *
     * @return array
     * @throws LpWoMissingWorkOrderException
     */
    public function choose($lpWoId)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);

        /** @var Person $person */
        $person = $personRepo->findSoft($lpWo->getPersonId());

        /** @var Crm $crm */
        $crm = $this->app->make(Crm::class);

        /** @var Crm $pdfDataService */
        $pdfDataService = $this->app->make(WorkOrderPdfDataService::class);

        $output = [
            'item' => $this->getItemStructure($lpWo),
            'empty_wo_description' => empty($lpWo->getQbInfo()),
            'missing_customer_settings' => empty($lpWo->workOrder->customer_setting_id),
            'tag' => $crm->getInstallation(),
            'attach_files' => $this->getAttachFiles(),
            'document_sections' => $this->getDocumentSections($pdfDataService, $lpWo->workOrder),
            'company' => [
                'preferred_contact' => $person ? $person->getCustom9() : null,
            ],
        ];

        // @TODO replace GFS validation case with common default validation.
        if ($crm->is('gfs')) {
            /** @var WorkOrderPdfDataService $pdfDataService */
            $pdfDataService = $this->app->make(WorkOrderPdfDataService::class);
            $output['errors_data'] =
                $this->getGfsErrors($pdfDataService, $lpWo->workOrder);
        }

        return $output;
    }

    /**
     * Get item structure (necessary data from link person wo and work order)
     *
     * @param LinkPersonWo $lpWo
     *
     * @return array
     */
    protected function getItemStructure(LinkPersonWo $lpWo)
    {
        return [
            'id' => $lpWo->getId(),
            'work_order' => [
                'id' => $lpWo->workOrder->getId(),
                'work_order_number' => $lpWo->workOrder->getWorkOrderNumber(),
            ],
        ];
    }

    /**
     * Generated WorkOrder PDF file
     *
     * @param int $lpWoId
     * @param Request $request
     *
     * @return array
     */
    public function generatePdf($lpWoId, Request $request)
    {
        /** @var WorkOrderPdfPrinter $pdfPrinter */
        $pdfPrinter = $this->app->make(WorkOrderPdfPrinter::class);
        list($fileName, $pdfFile) = $pdfPrinter->create($lpWoId, $request);

        $data = [
            'item' => [
                // @todo add access token here MANTIS-3842
                'link' => route('work_order.print_download', [$lpWoId]),
                'filename' => $fileName,
            ],
        ];

        return $data;
    }

    /**
     * Download Work Order PDF file
     *
     * @param $lpWoId
     * @param Request $request
     *
     * @return Response
     * @throws LpWoMissingWorkOrderException
     */
    public function download($lpWoId, Request $request)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        /** @var WorkOrderPdfPrinter $pdfPrinter */
        $pdfPrinter = $this->app->make(WorkOrderPdfPrinter::class);

        // @todo verify access token somewhere here MANTIS-3842

        list($fileName, $pdfFile) = $pdfPrinter->getFileInfo($lpWo);

        // file does not exits - throw exception, it has to be generated
        // using request parameter (we won't do it automatically)
        if (!file_exists($pdfFile)) {
            throw $this->app->make(ObjectNotFoundException::class);
        }

        $fileContent = file_get_contents($pdfFile);

        return response(
            $fileContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => strlen($fileContent),
                'Content-Disposition' =>
                    'attachment; filename="' . $fileName . '"',
                'Cache-Control' => '',
                'Pragma' => '',
            ]
        );
    }

    /**
     * Get data necessary to print e-mail action
     *
     * @param int $lpWoId
     *
     * @return array
     * @throws LpWoMissingWorkOrderException
     */
    public function showEmailInfo($lpWoId)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        /** @var WorkOrder $workOrder */
        $workOrder = $lpWo->workOrder;

        /** @var ContactRepository $contactRepo */
        $contactRepo = $this->app->make(ContactRepository::class);
        $toEmails = $contactRepo->getDefaultEmail($lpWo->getPersonId(), true);

        /** @var Crm $crm */
        $crm = $this->app->make(Crm::class);

        $errors = null;

        $pdfDataService = null;

        /** @var Trans $trans */
        $trans = $this->app->make(Trans::class);

        if ($crm->is('gfs')) {
            /** @var WorkOrderPdfDataService $pdfDataService */
            $pdfDataService = $this->app->make(WorkOrderPdfDataService::class);

            $company = $pdfDataService->getWorkOrderCompany($workOrder);
            $companyName = $company ? $company->getCustom1() : '';
            $subject = $trans->get('lpwo.work_order_pdf.email.subject_gfs', [
                'work_order_number' => $workOrder->getWorkOrderNumber(),
                'company_name' => $companyName,
                'fin_loc' => $workOrder->getFinLoc(),
            ]);
        } else {
            $subject = $trans->get('lpwo.work_order_pdf.email.subject', [
                'crm_user' => mb_strtoupper($crm->getInstallation()),
                'work_order_number' => $workOrder->getWorkOrderNumber(),
            ]);
        }

        $body = $subject;

        $output = [
            'email' => [
                'to' => $toEmails ? $toEmails[0] : null,
                'subject' => $subject,
                'body' => $body,
            ],
            'from_emails' => $this->getCurrentPersonFromEmails(),
            'to_emails' => $toEmails,
            'tag' => config('app.crm_user'),
            'attach_files' => $this->getAttachFiles(),
            'item' => $this->getItemStructure($lpWo),
        ];

        if ($crm->is('gfs')) {
            $output['errors_data'] =
                $this->getGfsErrors($pdfDataService, $workOrder);
        }

        return $output;
    }

    /**
     * Sends e-mail with generated Work order PDF file and issue work order
     *
     * @param int $lpWoId
     * @param Request $request
     *
     * @return LinkPersonWo
     * @throws mixed
     */
    public function sendEmail($lpWoId, Request $request)
    {
        // we need to generate file again to make sure it is what user expects
        // (and they might also change PDF settings when sending this request)
        /** @var WorkOrderPdfPrinter $pdfPrinter */
        $pdfPrinter = $this->app->make(WorkOrderPdfPrinter::class);
        list($fileName, $pdfFile) = $pdfPrinter->create($lpWoId, $request);

        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->find($lpWoId);

        $fromEmails = $this->getCurrentPersonFromEmails();

        // current user has no e-mail, we need to throw exception
        if (!$fromEmails) {
            $exp = $this->app->make(NoEmailForCurrentUserException::class);
            $exp->setData(['person_id' => getCurrentPersonId()]);
            throw $exp;
        }

        // set all data that will be needed to send e-mail
        $fromEmail = $fromEmails[0];
        $toEmail = $request->input('email_to');
        $subject = $request->input('email_subject');
        $description = $request->input('email_description');
        $fromName = config('app.company_full_name');

        $woConfirmationPhone =
            config('modconfig.work_order.confirmation.phone');

        $footerMessage = config('modconfig.work_order.confirmation.footer');

        // set confirmation text
        /** @var Trans $trans */
        $trans = $this->app->make(Trans::class);
        $woConfirmation = $trans->get('lpwo.work_order_pdf.confirm_via_email', [
            'confirmation_url' => $this->getWorkOrderConfirmationUrl($lpWo),
            'confirmation_phone' => $woConfirmationPhone,
        ]);

        // set e-mail body
        $message = $description . "\n\n" . $woConfirmation .
            "\n" . $footerMessage;

        // set e-mail attachments
        $attachments = [];
        $attachments[] = (object)[
            'location' => $pdfFile,
            'filename' => $fileName,
            'mime' => 'application/pdf',
        ];

        /** @var EmailSenderService $mailer */
        $mailer = $this->app->make(EmailSenderService::class);

        /** @var ActivityRepository $activityRepo */
        $activityRepo = $this->app->make(ActivityRepository::class);

        /** @var EmailRepository $emailRepo */
        $emailRepo = $this->app->make(EmailRepository::class);

        /** @var FileService $fileService */
        $fileService = $this->app->make(FileService::class);

        /** @var LinkPersonWoStatusService $lpWoStatusService */
        $lpWoStatusService = $this->app->make(LinkPersonWoStatusService::class);

        DB::transaction(function () use (
            $mailer,
            $activityRepo,
            $emailRepo,
            $fileService,
            $lpWoStatusService,
            $toEmail,
            $fromEmail,
            $subject,
            $message,
            $fromName,
            $attachments,
            $lpWo,
            $pdfFile
        ) {
            // send e-mail with attachment
            $mailer->sendPlain(
                $toEmail,
                $fromEmail,
                $subject,
                $message,
                $fromName,
                $attachments
            );
            // add activity
            $activityRepo->add(
                'work_order',
                $lpWo->getWorkOrderId(),
                'WO was emailed to ' . $toEmail,
                '',
                0,
                0,
                Activity::DIRECTION_OUT
            );

            // @todo should we set here direction to OUT ???

            // create e-mail record
            /** @var Email $email */
            $email = $emailRepo->forceCreate([
                'from_email' => $fromEmail,
                'to_email' => $toEmail,
                'cc_email' => '',
                'bcc' => '',
                'subject' => $subject,
                'body_plain' => $message,
                'date' => Carbon::now()->format('Y-m-d H:i:s'),
                'work_order_id' => $lpWo->getWorkOrderId(),
            ]);

            // add file (that was sent in e-mail) to e-mail record
            $fileService->saveFromLocal(
                $pdfFile,
                'File was emailed to ' . $toEmail,
                'email',
                $email->getId(),
                '',
                getCurrentPersonId()
            );

            // @todo below exceptions may be thrown and in this case e-mail will
            // be sent but data won't be inserted into database - need to decide
            // how it should work - should we catch exceptions for issue in this
            // case? because the main action is sending e-mail and we only try
            // to issue and don't care if it succeed?
            // issue work order
            $lpWoStatusService->issue($lpWo);
        });

        return $this->lpWoRepo->find($lpWoId);
    }

    /**
     * Get work order confirmation url
     *
     * @param LinkPersonWo $lpWo
     *
     * @return string
     */
    protected function getWorkOrderConfirmationUrl(LinkPersonWo $lpWo)
    {
        // @todo need to decide how it should work - should it be API url
        // or frontend url which after users logs in will call API method

        return route('work_order.confirm', [
            'id' => $lpWo->getId(),
            'via' => 'Email',
            'key' => strtoupper(md5($lpWo->getWorkOrderId())) . '-' .
                strtoupper(md5($lpWo->getId())),
        ]);
    }

    /**
     * Get current person from emails
     *
     * @return array
     */
    protected function getCurrentPersonFromEmails()
    {
        /** @var ContactRepository $contactRepo */
        $contactRepo = $this->app->make(ContactRepository::class);

        return $contactRepo->getDefaultEmail(getCurrentPersonId(), true);
    }

    /**
     * Get data necessary to print fax action
     *
     * @param int $lpWoId
     *
     * @return array
     * @throws LpWoMissingWorkOrderException
     */
    public function showFaxInfo($lpWoId)
    {
        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        /** @var WorkOrder $workOrder */
        $workOrder = $lpWo->workOrder;

        /** @var ContactRepository $contactRepo */
        $contactRepo = $this->app->make(ContactRepository::class);
        $faxes = $contactRepo->findValueForField(
            'person_id',
            $lpWo->getPersonId(),
            getTypeIdByKey('contact.fax'),
            false
        );
        $defaultFax = null;

        foreach ($faxes as $key => $fax) {
            $faxes[$key] =
                '1' . substr(preg_replace('/[^0-9]+/', '', $fax), -10);
            if ($key == 0) {
                $defaultFax = $faxes[$key];
            }
        }

        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);
        $vendorName = $personRepo->getPersonName($lpWo->getPersonId());

        $output = [
            'default_fax' => $defaultFax,
            'faxes' => $faxes,
            'vendor_name' => $vendorName,
            'tag' => config('app.crm_user'),
            'attach_files' => $this->getAttachFiles(),
            'item' => $this->getItemStructure($lpWo),
        ];

        /** @var Crm $crm */
        $crm = $this->app->make(Crm::class);

        if ($crm->is('gfs')) {
            /** @var WorkOrderPdfDataService $pdfDataService */
            $pdfDataService = $this->app->make(WorkOrderPdfDataService::class);
            $output['errors_data'] =
                $this->getGfsErrors($pdfDataService, $workOrder);
        }

        return $output;
    }

    /**
     * Upload Work order PDF to fax server and issue work order
     *
     * @param int $lpWoId
     * @param Request $request
     *
     * @return LinkPersonWo
     * @throws mixed
     */
    public function sendFax($lpWoId, Request $request)
    {
        // we need to generate file again to make sure it is what user expects
        // (and they might also change PDF settings when sending this request)
        /** @var WorkOrderPdfPrinter $pdfPrinter */
        $pdfPrinter = $this->app->make(WorkOrderPdfPrinter::class);
        list($fileName, $pdfFile) = $pdfPrinter->create($lpWoId, $request);

        /** @var LinkPersonWo $lpWo */
        $lpWo = $this->lpWoRepo->findWithWorkOrder($lpWoId);

        // get current person name
        /** @var PersonRepository $personRepo */
        $personRepo = $this->app->make(PersonRepository::class);
        $personName = $personRepo->getPersonName($lpWo->getPersonId());

        $faxNumber = $request->input('fax_number');

        // upload file to FAX FTP server
        /** @var FaxService $faxService */
        $faxService = $this->app->make(FaxService::class);
        $remoteFile = $faxService->send(
            $faxNumber,
            'WO#' . $lpWo->workOrder->getWorkOrderNumber(),
            $pdfFile,
            date('YmdHis') . '_' . $lpWoId . '_',
            '.pdf'
        );

        /** @var NotificationRepository $notificationRepo */
        $notificationRepo = $this->app->make(NotificationRepository::class);

        /** @var ActivityRepository $activityRepo */
        $activityRepo = $this->app->make(ActivityRepository::class);

        /** @var LinkPersonWoStatusService $lpWoStatusService */
        $lpWoStatusService = $this->app->make(LinkPersonWoStatusService::class);

        DB::transaction(function () use (
            $notificationRepo,
            $activityRepo,
            $lpWoStatusService,
            $lpWo,
            $remoteFile,
            $faxNumber,
            $personName
        ) {
            // add notification record
            $notificationRepo->add(
                $lpWo->getId(),
                'fax',
                $remoteFile,
                1,
                'Queued'
            );

            // add activity
            $activityRepo->add(
                'work_order',
                $lpWo->getWorkOrderId(),
                'WO Fax for ' . $personName . ', fax:' . $faxNumber .
                ' was queued.',
                '',
                0,
                0,
                Activity::DIRECTION_OUT
            );

            // @todo below exceptions may be thrown and in this case e-mail will
            // be sent but data won't be inserted into database - need to decide
            // how it should work - should we catch exceptions for issue in this
            // case? because the main action is sending e-mail and we only try
            // to issue and don't care if it succeed?
            // issue work order
            $lpWoStatusService->issue($lpWo);
        });

        return $this->lpWoRepo->find($lpWoId);
    }

    /**
     * Get GFS errors and data for errors
     *
     * @param WorkOrderPdfDataService $service
     * @param WorkOrder $workOrder
     *
     * @return mixed
     */
    protected function getGfsErrors(
        WorkOrderPdfDataService $service,
        WorkOrder $workOrder
    ) {
        $companySettings =
            $service->getSimpleCustomerSettings($workOrder);

        $errors = [];

        if (!$companySettings) {
            $errors['errors'][] = 'no_company_settings';
        } else {
            if (empty($companySettings->ivr_text_one)) {
                $errors['errors'][] = 'ivr_text_one_missing';
            }
            if (empty($companySettings->ivr_text_two)) {
                $errors['errors'][] = 'ivr_text_two_missing';
            }
            if (!empty($errors['errors'])) {
                $errors['data']['company_settings_id'] =
                    $companySettings->getId();
            }
        }

        return $errors;
    }

    /**
     * Get attach files
     *
     * @return array
     */
    protected function getAttachFiles()
    {
        $files = config('modconfig.work_order.issue_append_files', []);
        $data = [];
        foreach ($files as $path => $name) {
            $data[] = (object)[
                'value' => $path,
                'label' => $name,
            ];
        }

        return $data;
    }

    /**
     * Get document sections
     *
     * @param WorkOrderPdfDataService $service
     * @param WorkOrder $workOrder
     *
     * @return mixed
     */
    protected function getDocumentSections(
        WorkOrderPdfDataService $service,
        WorkOrder $workOrder
    ) {

        // We need only fields from footer section
        return $service->getDocumentSections($workOrder, 'work_order', 'footer');
    }
}
