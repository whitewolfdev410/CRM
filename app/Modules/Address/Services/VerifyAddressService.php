<?php

namespace App\Modules\Address\Services;

use App\Core\CommandTrait;
use App\Core\Trans;
use App\Modules\Address\Exceptions\VerifyAddressSendEmailException;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Models\AddressVerifyStatus;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\Address\Exceptions\VerifyAddressGeocodingException;
use App\Modules\Email\Services\EmailSenderService;
use Carbon\Carbon;
use Exception;
use Geocoder\Model\AdminLevel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class VerifyAddressService
{
    use CommandTrait;

    /**
     * @var Container
     */
    protected $app;

    /**
     * @var AddressRepository
     */
    protected $addressRepo;

    /**
     * @var AddressGeocoderService
     */
    private $service;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param AddressRepository $addressRepo
     * @param AddressGeocoderService $service
     */
    public function __construct(
        Container $app,
        AddressRepository $addressRepo,
        AddressGeocoderService $service
    ) {
        $this->app = $app;
        $this->addressRepo = $addressRepo;
        $this->service = $service;
    }

    /**
     * Verify address record and if state is not as expected send e-mail
     * notification
     *
     * @param int $number
     */
    public function run($number = 10)
    {
        $invalidAddresses = [];
        $geocodingData = [];

        // get unverified addresses - latest added/modified first
        $unverified = $this->addressRepo->getUnverified(
            $number,
            'date_modified DESC, address_id DESC'
        );

        // no unverified addresses - return message
        if (!$unverified->count()) {
            $this->log('All addresses are already verified');

            return;
        }

        $this->service->setTerminal($this->terminal);

        // process in loop over addresses
        foreach ($unverified as $address) {
            /** @var Address $address */

            try {
                // get geocoding data
                /**  @var \Geocoder\Model\Address $geocoding */
                [$geocoding, $accuracy] = $this->service->geocode($address);

                // get admin levels and assume the first one is state
                $state = $geocoding->getAdminLevels()->first();

                // get state from address
                $addressState = $address->getState();
                // verify if given state matches name or code from geocoding
                if (!$this->isStateSame($addressState, $state)) {
                    // assign geocoding data to be used later
                    $geocodingData[$address->getId()] =
                        ['geocoding' => $geocoding, 'state' => $state];

                    // assign this address to invalid addresses
                    $invalidAddresses[] = $address;
                } else {
                    // change status to verified
                    $this->addressRepo->updateVerifyStatus(
                        $address,
                        AddressVerifyStatus::VERIFIED
                    );

                    // log to console - state was valid
                    $this->log('Address #' . $address->getId() .
                        ' - everything was fine');
                }
            } catch (Exception $e) {
                // log info to console
                $this->log(
                    'Address #' . $address->getId() . ' - there was a ' .
                    "problem with geocoding (details in general log file). This geocoding won't be repeated anymore",
                    'error'
                );

                // log exception
                $exp = $this->app->make(VerifyAddressGeocodingException::class);
                $exp->setData([
                    'address_id' => $address->getId(),
                    'exception' => (string)$e,
                ]);
                $exp->log();

                // update status to verify error
                $this->addressRepo->updateVerifyStatus(
                    $address,
                    AddressVerifyStatus::VERIFY_ERROR
                );
            }
        }

        if (!$invalidAddresses) {
            $this->log('No invalid addresses found.');

            return;
        }

        // now we have all invalid addresses

        $notifyAddresses = [];

        foreach ($invalidAddresses as $address) {
            // 1. get fresh record to not send double notifications
            $address = $this->addressRepo->findSoft($address->getId());

            // 2. if address is already verified continue (just in case)
            if ($address->getVerified()) {
                // log to console
                $this->log('Address #' . $address->getId() .
                    ' was already verified', 'info');

                continue;
            }

            // add this address to be notified
            $notifyAddresses[] = $address;
        }

        // no notify addresses - nothing to do
        if (!$notifyAddresses) {
            return;
        }

        // now we need to update verified status + send email for those addresses
        DB::beginTransaction();

        try {
            // change address status to verified
            foreach ($notifyAddresses as $address) {
                $this->addressRepo->updateVerifyStatus(
                    $address,
                    AddressVerifyStatus::VERIFIED
                );
            }

            // notify that addresses have probably invalid state
            $this->notifyDifferentState($notifyAddresses, $geocodingData);

            /** @var Address $address */
            foreach ($notifyAddresses as $address) {
                // set log message
                $logMessage =
                    'Address #' . $address->getId() . ' - expected state: '
                    . $geocodingData[$address->getId()]['state']->getName() .
                    ', zip code: ' .
                    $geocodingData[$address->getId()]['geocoding']->getPostalCode()
                    . ', found state: ' . $address->getState() . ', zip code: '
                    . $address->getZipCode();

                // log to log
                $this->app->log->info($logMessage . '. E-mail was sent.');

                // log to console
                $this->log($logMessage, 'info');
            }
            $this->log('E-mail with invalid states has been sent.', 'info');
        } catch (Exception $e) {
            DB::rollback();

            // log to console
            $this->log(
                'Unexpected error occurred when trying to send e-mail report',
                'error'
            );

            // log exception
            $exp = $this->app->make(VerifyAddressSendEmailException::class);
            $exp->setData(['exception' => (string)$e, ]);
            $exp->log();
        }

        DB::commit();
    }

    /**
     * Send e-mail notification that states are different
     *
     * @param array $addresses
     */
    protected function notifyDifferentState(
        array $addresses,
        array $geocodingData
    ) {
        /** @var EmailSenderService $service */
        $service = $this->app->make(EmailSenderService::class);
        $url =
            $this->app->config->get('email.actions.address_verify_incorrect_state.url');

        // set edit url for each address
        foreach ($addresses as $address) {
            $address->edit_url =
                str_replace('{address_id}', $address->getId(), $url);
        }

        // set valid subject
        $trans = $this->app->make(Trans::class);
        $subject =
            $trans->get(
                'notification.address_verify_incorrect_state.topic',
                ['date' => Carbon::now()->format('Y-m-d H:i:s')]
            );

        // send e-mail
        $service->sendType(
            'address_verify_incorrect_state',
            $subject,
            view(
                'emails.notifications.invalid_state',
                [
                    'addresses' => $addresses,
                    'geocoding' => $geocodingData,
                ]
            )->render()
        );
    }

    /**
     * Verify if state is the same
     *
     * @param string $addressState
     * @param AdminLevel $geocoderState
     * @return bool
     */
    protected function isStateSame($addressState, AdminLevel $geocoderState)
    {
        $addressState = $this->normalizeState($addressState);

        if ($addressState == $this->normalizeState($geocoderState->getName()) ||
            $addressState == $this->normalizeState($geocoderState->getCode())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Normalize state
     *
     * @param string $stateName
     *
     * @return string
     */
    protected function normalizeState($stateName)
    {
        return mb_strtoupper(trim($stateName));
    }
}
