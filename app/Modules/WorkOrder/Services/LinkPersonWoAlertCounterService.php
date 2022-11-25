<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\CalendarEvent\Repositories\CalendarEventRepository;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Config\Repository as Config;

class LinkPersonWoAlertCounterService
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
     * @var CalendarEventRepository
     */
    private $calRepo;

    /**
     * @var Config
     */
    protected $config;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param LinkPersonWoRepository $lpWoRepo
     * @param CalendarEventRepository $calRepo
     */
    public function __construct(
        Container $app,
        LinkPersonWoRepository $lpWoRepo,
        CalendarEventRepository $calRepo
    ) {
        $this->app = $app;
        $this->lpWoRepo = $lpWoRepo;
        $this->config = $app->config;
        $this->calRepo = $calRepo;
    }

    /**
     * Get alert counter data
     *
     * @return array
     */
    public function get()
    {
        $personId = getCurrentPersonId();
        $issuedCount = $this->lpWoRepo->getIssuedCount($personId);
        $calInProgress = $this->calRepo->getNotCompletedCount($personId);

        return [
            'issued_wo' => $issuedCount,
            'calendar_events' => $calInProgress,
        ];
    }
}
