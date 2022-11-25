<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;

class WorkOrderCompletionGridService
{
    /**
     * @var WorkOrderRepository
     */
    private $woRepository;

    /**
     * @var Container
     */
    private $app;

    public function __construct(
        WorkOrderRepository $woRepository,
        Container $app
    ) {
        $this->woRepository = $woRepository;
        $this->app = $app;
    }


    protected function getDefaultOrder()
    {
        'created_date DESC'; // @todo
    }
}
