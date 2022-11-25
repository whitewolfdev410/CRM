<?php

namespace App\Modules\Person\Services;

use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use Illuminate\Contracts\Container\Container;

class PersonConfigService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var PersonRepository
     */
    protected $personRepo;

    /**
     * @var TimeSheetRepository
     */
    protected $timeSheetRepo;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param PersonRepository $personRepo
     * @param TimeSheetRepository $timeSheetRepo
     */
    public function __construct(
        Container $app,
        PersonRepository $personRepo,
        TimeSheetRepository $timeSheetRepo
    ) {
        $this->app = $app;
        $this->personRepo = $personRepo;
        $this->timeSheetRepo = $timeSheetRepo;
    }
    
    /**
     * Get person mobile configuration together with person_id and person_name
     *
     * @return mixed
     */
    public function getMobileConfig()
    {
        $data['settings'] = $this->app->config->get('mobile.settings');

        $personId = getCurrentPersonId();
        $data['person'] = [
            'id' => $personId,
            'name' => $this->personRepo->getPersonName($personId),
            'has_ongoing_timesheet' =>
                ($this->timeSheetRepo->getOngoingTimeSheet()) ? true : false,
        ];

        return $data;
    }
}
