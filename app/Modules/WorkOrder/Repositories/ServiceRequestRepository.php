<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\WorkOrder\Models\ServiceRequest;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * ServiceRequest repository class
 */
class ServiceRequestRepository extends AbstractRepository
{

    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [

            'company_person_id',
            'crm_priority_type_id',
            'billing_company_person_id',
            'shop_address_id',
            'request_date',
            'trade_type_id',
            'category_type_id',
            'expected_completion_date',
            'description',
            'not_to_exceed'
            // when adding new filters consider change in $joinableFilters
        ];

    /**
     * Columns that might be used for sorting
     *
     * @var array
     */
    protected $sortable = [];

    /**
     * Custom filters
     *
     * @var array
     */
    protected $customFilters
        = [
            'company_person_id',
            'crm_priority_type_id',
            'billing_company_person_id',
            'shop_address_id',
            'request_date',
            'trade_type_id',
            'category_type_id',
            'expected_completion_date',
            'description',
            'not_to_exceed'
            // when adding new filters consider change in $joinableFilters
        ];

    /**
     * List of filters (from both $searchable and $customFilters) that require
     * making join for count query. Whenever you add new filter
     * for $customFilters or $searchable that require join you should add
     * this filter also here with type of join it will require
     *
     * Type of join may be also array, possible values comes from methods from
     * WorkOrderQueryGeneratorService and are address, trade_type, cancel_type,
     * priority_type
     *
     * @var array
     */
    protected $joinableFilters
        = [
            'state'   => 'address',
            'city'    => 'address',
            'country' => 'address',
        ];


    /**
     * Repository constructor
     *
     * @param Container      $app
     * @param ServiceRequest $serviceRequest
     */
    public function __construct(Container $app, ServiceRequest $serviceRequest)
    {
        parent::__construct($app, $serviceRequest);

        $this->type = $this->makeRepository('Type');
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function create(array $input)
    {
        $model = null;

        DB::transaction(function () use (
            $input,
            &$model
        ) {

            // manual creation - we want to set custom fillable fields
            /** @var ServiceRequest $model */
            $model = $this->newInstance();
            $model->setFillableType('create');
            $model->fill($input);
            $model->save();

            // clear fillable fields to avoid any unpredicted results
            $model->clearFillable();
        });

        return [$model];
    }
}
