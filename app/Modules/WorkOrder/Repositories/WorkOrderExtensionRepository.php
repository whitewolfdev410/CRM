<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\WorkOrderExtension;
use Illuminate\Container\Container;
use App\Modules\WorkOrder\Http\Requests\WorkOrderExtensionRequest;
use Illuminate\Support\Facades\DB;

/**
 * WorkOrderExtension repository class
 */
class WorkOrderExtensionRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param WorkOrderExtension $workOrderExtension
     */
    public function __construct(
        Container $app,
        WorkOrderExtension $workOrderExtension
    ) {
        parent::__construct($app, $workOrderExtension);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new WorkOrderExtensionRequest();

        return $req->getFrontendRules();
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $input)
    {
        $input['person_id'] = getCurrentPersonId();
        $wo = $this->getRepository('WorkOrder');

        $object = null;
        $changed = null;

        DB::transaction(function () use (&$object, $input, $wo) {
            $object = $this->model->create($input);
            $wo->updateExpectedCompletionDate(
                $object->getWorkOrderId(),
                $input['extended_date']
            );
        });

        if ($object) {
            $changed['work_order'] = [
                'expected_completion_date' => $input['extended_date'],
            ];
        }

        return [$object, $changed];
    }
}
