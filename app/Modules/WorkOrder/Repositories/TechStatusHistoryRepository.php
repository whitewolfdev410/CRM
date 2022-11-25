<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Http\Requests\TechStatusHistoryRequest;
use App\Modules\WorkOrder\Models\TechStatusHistory;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * TechStatusHistory repository class
 */
class TechStatusHistoryRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container         $app
     * @param TechStatusHistory $techStatusHistory
     */
    public function __construct(
        Container $app,
        TechStatusHistory $techStatusHistory
    ) {
        parent::__construct($app, $techStatusHistory);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new TechStatusHistoryRequest();

        return $req->getFrontendRules();
    }

    /**
     * Search entry with given data
     *
     * @param array $data
     *
     * @return TechStatusHistory
     */
    public function findWithData($data)
    {
        return $this->model
            ->where('link_person_wo_id', '=', $data['link_person_wo_id'])
            ->where('current_tech_status_type_id', '=', $data['current_tech_status_type_id'])
            ->where('previous_tech_status_type_id', '=', $data['previous_tech_status_type_id'])
            ->where('changed_at', '=', $data['changed_at'])
            ->first();
    }

    /**
     * Get tech status history by link_person_wo_id
     *
     * @param      $linkPersonWoId
     * @param null $startDate
     * @param null $endDate
     *
     * @return mixed
     */
    public function getHistoryByLinkPersonWoId($linkPersonWoId, $startDate = null, $endDate = null)
    {
        $model = $this->model
            ->select([
                'current_tech_status_type_id',
                'previous_tech_status_type_id',
                'changed_at',
            ])
            ->where('link_person_wo_id', $linkPersonWoId);

        if ($startDate) {
            $model = $model->where('changed_at', '>=', $startDate);
        }

        if ($endDate) {
            $model = $model->where('changed_at', '<=', $endDate);
        }

        return $model->get();
    }

    /**
     * Get tech status history by work_order_id
     *
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getHistoryByWorkOrderId($workOrderId)
    {
        return $this->model
            ->select([
                'tech_status_history.id',
                'tech_status_history.previous_tech_status_type_id',
                'tech_status_history.current_tech_status_type_id',
                DB::raw('person_name(link_person_wo.person_id) as technician_name'),
                DB::raw('t1.type_value AS previous_status'),
                DB::raw('t2.type_value AS current_status'),
                'tech_status_history.created_at',
            ])
            ->leftJoin('type as t1', 'tech_status_history.previous_tech_status_type_id', '=', 't1.type_id')
            ->leftJoin('type as t2', 'tech_status_history.current_tech_status_type_id', '=', 't2.type_id')
            ->leftJoin('link_person_wo', 'link_person_wo.link_person_wo_id', '=', 'tech_status_history.link_person_wo_id')
            ->where('link_person_wo.work_order_id', $workOrderId)
            ->orderBy('tech_status_history.created_at')
            ->orderBy('tech_status_history.id')
            ->get();
    }
}
