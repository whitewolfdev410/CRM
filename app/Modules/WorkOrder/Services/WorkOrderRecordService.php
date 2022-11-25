<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\WorkOrder\Models\WorkOrder;
use Illuminate\Contracts\Container\Container;

class WorkOrderRecordService
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

    public function created(WorkOrder $wo)
    {
        // @todo what is assigned_to_person_id here?

        //if (empty($wo->getProjectManagerPersonId()) && !empty($wo->))
    }
}
