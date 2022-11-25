<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderTemplate;
use App\Modules\WorkOrder\Services\WorkOrderTemplateService;
use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder;

/**
 * WorkOrder repository class
 */
class WorkOrderTemplateRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'template_name'
    ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [
        'template_name'
    ];

    /**
     * Repository constructor
     *
     * @param Container         $app
     * @param WorkOrderTemplate $workOrderTemplate
     */
    public function __construct(Container $app, WorkOrderTemplate $workOrderTemplate)
    {
        parent::__construct($app, $workOrderTemplate);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($perPage = 50, array $columns = ['*'], array $order = [])
    {
        /** @var WorkOrderTemplate|Builder $model */
        $model = $this->model;

        /** @var Object $model */
        $this->setWorkingModel($model);

        // get data and clear model
        $data = parent::paginate($perPage, $columns, $order);

        $this->clearWorkingModel();

        /** @var LinkPersonWoTemplateRepository $linkPersonWoTemplateRepository */
        $linkPersonWoTemplateRepository = app(LinkPersonWoTemplateRepository::class);
        $workOrderTemplateIds = array_column($data->items(), 'work_order_template_id');

        $vendors = $linkPersonWoTemplateRepository->getByWorkOrderTemplateIds($workOrderTemplateIds);
        
        foreach ($data->items() as $item) {
            $item->assign_to_person_ids = isset($vendors[$item->work_order_template_id])
                ? $vendors[$item->work_order_template_id]
                : [];
        }
        
        return $data;
    }
}
