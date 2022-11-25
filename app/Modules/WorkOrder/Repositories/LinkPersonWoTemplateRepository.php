<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\LinkPersonWoTemplate;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * LinkPersonWoTemplate repository class
 */
class LinkPersonWoTemplateRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [

    ];

    /**
     * {@inheritdoc}
     */
    protected $sortable = [

    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  LinkPersonWoTemplate  $linkPersonWoTemplate
     */
    public function __construct(Container $app, LinkPersonWoTemplate $linkPersonWoTemplate)
    {
        parent::__construct($app, $linkPersonWoTemplate);
    }

    /**
     * @param $workOrderTemplateId
     *
     * @return mixed
     */
    public function getByWorkOrderTemplateId($workOrderTemplateId)
    {
        $result = $this->model
            ->select([
                'link_person_wo_template.*',
                DB::raw('person_name(link_person_wo_template.person_id) as person_name'),
                DB::raw('t(link_person_wo_template.status_type_id) as status_type_id_value')
            ])
            ->where('work_order_template_id', $workOrderTemplateId)
            ->get();
        
        foreach ($result as $index => $item) {
            $item->lpwo_id = $index + 1;
        }
        
        return $result;
    }

    /**
     * @param $workOrderTemplateIds
     *
     * @return mixed
     */
    public function getByWorkOrderTemplateIds($workOrderTemplateIds)
    {
        $groupedByWorkOrderTemplate = [];
        
        if ($workOrderTemplateIds) {
            $result = $this->model
                ->select([
                    'person_id',
                    'work_order_template_id'
                ])
                ->whereIn('work_order_template_id', $workOrderTemplateIds)
                ->get();

            foreach ($result as $item) {
                $groupedByWorkOrderTemplate[$item->work_order_template_id][] = $item->person_id;
            }
        }
        
        return $groupedByWorkOrderTemplate;
    }
}
