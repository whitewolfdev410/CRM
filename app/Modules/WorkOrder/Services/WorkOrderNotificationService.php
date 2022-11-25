<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\Email\Services\EmailSenderService;
use App\Modules\EmailTemplate\Providers\OrganizationFieldsProvider;
use App\Modules\EmailTemplate\Services\EmailTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;

class WorkOrderNotificationService
{

    /**
     * App
     *
     * @var Container
     */
    protected $app;

    /**
     * Config
     *
     * @var mixed
     */
    protected $config;

    /**
     * @var EmailSenderService
     */
    protected $emailService;
    
    
    /**
     * Initialize fields
     *
     * @param Container $app
     * @param EmailSenderService $emailService
     */
    public function __construct(
        Container $app,
        EmailSenderService $emailService
    ) {
        $this->app = $app;
        $this->config = $app->config;

        $this->emailService = $emailService;
    }


    /**
     * Update work order status
     * @param $woId
     * @return bool
     */
    public function sendNotification($woId)
    {
        if ($this->config->get('crm_settings.wo_notification_enabled', 0)) {
            $emailTypeID = getTypeIdByKey('contact.email');
            $scrubTypeID = getTypeIdByKey('via.scrub');
            if ($woId > 0 && $emailTypeID > 0 && $scrubTypeID > 0) {
                $workOrder = new WorkOrder();
                $workOrder = $workOrder->find($woId);
                if ($workOrder->getCompanyPersonId() > 0 && $workOrder->getViaTypeId() == $scrubTypeID) {
                    try {
                        // get company emails
                        $companyPersonID = $workOrder->getCompanyPersonId();
                        $emails = DB::select(DB::raw("SELECT * from contact where type_id = {$emailTypeID} and person_id = {$companyPersonID} and name = 'wo_create_notification'; "));
                        $contactEmailAddress = [];
                        foreach ($emails as $k => $l) {
                            $contactEmailAddress[] = $l->value;
                        }
                        // set temaplate and send email company emails
                        if (empty($contactEmailAddress)) {
                            Log::error('WorkOrderNotificationService:sendNotification - No email for company:'.$companyPersonID);
                            return false;
                        } else {
                            $service = $this->app->make(EmailTemplateService::class);
        
                            $template = $service->mergeByTemplateId(
                                'wo.create_notification',
                                'en-US',
                                [],
                                [
                                        OrganizationFieldsProvider::NAME => new OrganizationFieldsProvider(),
                                    ]
                            );
    
                            $email = [
                                'bcc_email'  => $template->getBccEmail(),
                                'body'       => $template->getBody(),
                                'cc_email'   => $template->getCcEmail(),
                                'from_email' => $template->getFromEmail(),
                                'from_name'  => $template->getFromName(),
                                'subject'    => $template->getSubject(),
                            ];
                            
                            foreach ($contactEmailAddress as $to_email) {
                                $wasSent = $this->emailService->sendAdvancedEmail(
                                    $to_email,
                                    $email['from_email'],
                                    $email['cc_email'],
                                    $email['bcc_email'],
                                    $email['subject'],
                                    $email['body']
                                );
    
                                if ($wasSent) {
                                    return true;
                                }
                            }
                            return true;
                        }
                    } catch (Exception $e) {
                        Log::error('WorkOrderNotificationService:sendNotification - Error', [
                            $e->getMessage()
                        ]);
                        return false;
                    }
                }
            }
        }
        return false;
    }
}
