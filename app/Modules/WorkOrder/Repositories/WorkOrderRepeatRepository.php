<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderRepeat;
use Illuminate\Container\Container;

/**
 * WorkOrder repository class
 */
class WorkOrderRepeatRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
    ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  WorkOrderRepeat  $workOrderRepeat
     */
    public function __construct(Container $app, WorkOrderRepeat $workOrderRepeat)
    {
        parent::__construct($app, $workOrderRepeat);
    }

    /**
     * @param $workOrderTemplateId
     *
     * @return mixed
     */
    public function getByWorkOrderTemplateId($workOrderTemplateId)
    {
        $workOrderRepeat = $this->model
            ->where('work_order_template_id', $workOrderTemplateId)
            ->first();
        
        if ($workOrderRepeat) {
            $workOrderRepeat->interval_keyword = strtoupper($workOrderRepeat->interval_keyword);
        }
        
        return $workOrderRepeat;
    }
}
