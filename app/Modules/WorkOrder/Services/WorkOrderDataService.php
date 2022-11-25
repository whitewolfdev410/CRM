<?php

namespace App\Modules\WorkOrder\Services;

use App\Modules\File\Models\File;
use App\Modules\TimeSheet\Services\TimeSheetService;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Repositories\TechStatusHistoryRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Class WorkOrderDataService
 *
 * Get necessary data for WorkOrder module (used in selects or displaying colour
 * boxes)
 *
 * @package App\Modules\WorkOrder\Services
 */
class WorkOrderDataService implements WorkOrderDataServiceContract
{
    /**
     * Type repository
     *
     * @var TypeRepository
     */
    protected $type;

    /**
     * Person repository
     *
     * @var \App\Modules\Person\Repositories\PersonRepository
     */
    protected $person;

    /**
     * Company repository
     *
     * @var \App\Modules\Person\Repositories\CompanyRepository
     */
    protected $company;

    /**
     * Work order repository
     *
     * @var WorkOrderRepository
     */
    protected $workOrder;

    /**
     * Country repository
     *
     * @var \App\Modules\Address\Repositories\CountryRepository
     */
    protected $country;

    /**
     * State repository
     *
     * @var \App\Modules\Address\Repositories\StateRepository
     */
    protected $state;

    /**
     * @var Container
     */
    protected $app;

    /**
     * Initialize repositories
     *
     * @param TypeRepository      $typeRepository
     * @param WorkOrderRepository $woRepository
     * @param Container           $app
     */
    public function __construct(
        TypeRepository $typeRepository,
        WorkOrderRepository $woRepository,
        Container $app
    ) {
        $this->type = $typeRepository;

        $this->person = $typeRepository->makeRepository('Person', 'Person');
        $this->company = $typeRepository->makeRepository('Company', 'Person');
        $this->workOrder = $woRepository;
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function getAll()
    {
        $this->country = $this->type->makeRepository('Country', 'Address');
        $this->state = $this->type->makeRepository('State', 'Address');

        list($data['company_person_id']['companies'], $idCompanies)
            = $this->getCompanyList();

        list($data['company_person_id']['persons'], $idIndividuals)
            = $this->getIndividualCustomerList();

        $data['client_type_id'] = $this->getClientTypesList(
            $idCompanies,
            $idIndividuals
        );

        $types = $this->getTypes();
        
        unset($types['quote_status']);
        
        $data = array_merge($data, $types);

        $data['state'] = $this->getStatesList();

        $data['client_status'] = $this->getClientStatusList();

        $data['billing_company_person_id'] = $this->getBillingCompanyList();

        $data['assigned_to_tech'] = $this->getTechnicianList();

        $data['assigned_to_vendor'] = $this->getVendorList();

        $data['project_manager_person_id'] = $this->getProjectManagerList();

        return $this->formatData($data);
    }

    /**
     * Get data for creating new work order record
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getRecordCreateData()
    {
        list($data['company_person_id']['companies'], $idCompanies)
            = $this->getCompanyList();

        list($data['company_person_id']['persons'], $idIndividuals)
            = $this->getIndividualCustomerList();

        if (config('app.crm_user') === 'fs') {
            $types = $this->getTypes([
                'crm_priority_type_id',
                'invoice_status_type_id',
                'parts_status_type_id',
                'quote_status_type_id',
                'via_type_id',
            ]);
        } else {
            $types = $this->getTypes([
                'crm_priority_type_id',
                'invoice_status_type_id',
                'parts_status_type_id',
                'quote_status_type_id',
                'tech_trade_type_id',
                'trade_type_id',
                'via_type_id',
                'wo_type_id',
            ]);
        }
        
        $data = array_merge($data, $types);

        // detailed list
        $data['billing_company_person_id']
            = $this->getDetailedBillingCompanyList();

        // detailed list - not based on work_orders
        $data['project_manager_person_id']
            = $this->getDetailedProjectManagerList();

        $data['estimated_time'] = $this->getEstimatedTimeList();
        
        $data = $this->formatData($data);

        // default invoice_status_type_id for create
        $data['invoice_status_type_id']['default_value']
            = $this->type->getIdByKey('wo_billing_status.update_required');

        if (config('app.crm_user') !== 'fs') {
            $emptyFields = [
                'wo_type_id',
                'region_id',
                'equipment_needed',
                'equipment_needed_text',
                'mapped_trade_id',
                'tech_trade_type_id',
                'owner_person_id',
                'sales_person_id'
            ];

            foreach ($emptyFields as $field) {
                $data[$field] = [
                    'rules' => []
                ];
            }

            $data['supplier_person_id'] = $this->getSupplierList();
            $data['sales_person_id']['access'] =  Auth::user()->hasPermissions(['workorder.edit-sales-person']);
        } else {
            $data['actual_completion_date'] = [
                'rules' => []
            ];
        }
        
        return $data;
    }

    /**
     * Get data for updating work order record
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getRecordUpdateData()
    {
        list($data['company_person_id']['companies'], $idCompanies)
            = $this->getCompanyList();

        list($data['company_person_id']['persons'], $idIndividuals)
            = $this->getIndividualCustomerList();

        $types = $this->getTypes([
            'crm_priority_type_id',
            'invoice_status_type_id',
            'parts_status_type_id',
            'quote_status_type_id',
            'tech_trade_type_id',
            'trade_type_id',
            'via_type_id',
            'wo_type_id',
        ]);
        $data = array_merge($data, $types);

        // detailed list
        $data['billing_company_person_id']
            = $this->getDetailedBillingCompanyList();

        // detailed list - not based on work_orders
        $data['project_manager_person_id']
            = $this->getDetailedProjectManagerList();

        $data['supplier_person_id'] = $this->getSupplierList();
        $data['estimated_time'] = $this->getEstimatedTimeList();

        $data = $this->formatData($data);

        // default invoice_status_type_id for create
        $data['invoice_status_type_id']['default_value']
            = $this->type->getIdByKey('wo_billing_status.update_required');

        return $data;
    }

    public function getEmployees()
    {
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function getValues()
    {
        $data = $this->getTypes([
            'via_type_id',
            'wo_status_type_id',
            'bill_status_type_id',
            'invoice_status_type_id',
            'quote_status_type_id',
        ]);

        list($data['company_person_id']['companies'], $idCompanies)
            = $this->getCompanyList();

        list($data['company_person_id']['persons'], $idIndividuals)
            = $this->getIndividualCustomerList();

        return $this->formatData($data);
    }

    /**
     * Format data
     *
     * @param $data
     *
     * @return array
     */
    protected function formatData(array $data)
    {
        $out = [];
        foreach ($data as $field => $values) {
            $out[$field]['data'] = $values;
        }

        return $out;
    }

    /**
     * Get company list together with ids of company.customer type
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getCompanyList()
    {
        $customers = $this->type->getIdByKey('company.customer', true);
        if (!is_array($customers)) {
            $customers = [$customers];
        }

        $owners = $this->type->getIdByKey('company.owner', true);
        if (!is_array($owners)) {
            $owners = [$owners];
        }

        $ids = array_merge($customers, $owners);

        return [
            $this->company
                ->getWoList($ids, $this->type->getIdByKey('company_status.active')),
            $ids,
        ];
    }

    /**
     * Get persons list together with ids of person.fence_residential_customer
     * type
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getIndividualCustomerList()
    {
        $ids = $this->type
            ->getIdByKey('person.fence_residential_customer', true);

        return [$this->person->getWoList($ids), $ids];
    }

    /**
     * Get client types list
     *
     * @param array $idCompanies
     * @param array $idIndividuals
     *
     * @return array
     */
    protected function getClientTypesList(
        array $idCompanies,
        array $idIndividuals
    ) {
        $output = [];

        $ids = array_merge($idCompanies, $idIndividuals);

        if ($ids) {
            $output = $this->type->getListByIds($ids);
        }

        return $output;
    }

    /**
     * Get types list for multiple fields and format it to match field names
     *
     * @param array $chosenTypes If empty get all types
     *
     * @return array
     */
    public function getTypes(array $chosenTypes = [])
    {
        $allTypes = [
            'wo_status'            => 'wo_status_type_id',
            'wo_quote_status2'     => 'quote_status_type_id',
            'via'                  => 'via_type_id',
            'wo_billing_status'    => 'invoice_status_type_id',
            'bill_status'          => 'bill_status_type_id',
            'person'               => 'person_type_id',
            'company'              => 'person_type_id',
            'quote_status'         => 'quote_status',
            'wo_part_status'       => 'parts_status_type_id',
            'crm_priority'         => 'crm_priority_type_id',
            'vendor_cancel_reason' => 'cancel_reason_type_id',
            'company_trade'        => 'trade_type_id',
            'wo_type'              => 'wo_type_id',
        ];

        if (empty($chosenTypes)) {
            $selectedTypes = array_keys($allTypes);
        } else {
            $selectedTypes = [];
            foreach ($allTypes as $k => $v) {
                if (in_array($v, $chosenTypes)) {
                    $selectedTypes[] = $k;
                }
            }
        }

        $types = $this->type->getMultipleLists($selectedTypes);

        $output = [];

        foreach ($types as $type => $value) {
            if (isset($output[$allTypes[$type]])) {
                $output[$allTypes[$type]] += $value;
            } else {
                $output[$allTypes[$type]] = $value;
            }
        }

        return $output;
    }

    /**
     * Get list of states and countries (at the moment only US-states
     * and US-country are returned)
     *
     * @return array
     */
    protected function getStatesList()
    {
        $countries = ['US']; // allowed countries
        $data['countries'] = $this->country->getList($countries);
        $data['states'] = $this->state->getList($countries);

        return $data;
    }

    /**
     * Get client status list
     *
     * @return array
     */
    protected function getClientStatusList()
    {
        return $this->workOrder->getClientStatusList();
    }

    /**
     * Get billing company list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getBillingCompanyList()
    {
        $id = $this->type->getIdByKey('company.billing_company');

        return $this->company->getWoList([$id]);
    }

    /**
     * Get detailed billing company list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getDetailedBillingCompanyList()
    {
        $id = $this->type->getIdByKey('company.billing_company');
        $idC = $this->type->getIdByKey('company.customer');
        $idO = $this->type->getIdByKey('company.owner');

        return $this->company->getWoList([$id, $idC, $idO], [], true);
    }

    /**
     * Get technician list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getTechnicianList()
    {
        $idE = $this->type->getIdByKey('person.employee');
        $idT = $this->type->getIdByKey('person.technician');

        return $this->person->getWoList([$idE, $idT], [], false);
    }

    /**
     * Get vendor list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getVendorList()
    {
        $idV = $this->type->getIdByKey('company.vendor');
        $idS = $this->type->getIdByKey('company.supplier');

        return $this->person->getWoList([$idV, $idS], [], false);
    }

    /**
     * Get project manager list
     *
     * @return array
     */
    public function getProjectManagerList()
    {
        $list = $this->workOrder->getProjectsManagersList();
        
        return array_filter($list, function ($item) {
            return !empty($item);
        });
    }

    /**
     * Get detailed project manager list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getDetailedProjectManagerList()
    {
        $idE = $this->type->getIdByKey('person.employee');
        $idDis = $this->type->getIdByKey('company_status.disabled');

        return $this->person->getWoList([$idE], $idDis, false, true);
    }

    /**
     * Get supplier list
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    protected function getSupplierList()
    {
        $idS = $this->type->getIdByKey('company.supplier');

        return $this->person->getWoList([$idS], [], false);
    }

    /**
     * Get estimated time list
     *
     * @return array
     */
    public function getEstimatedTimeList()
    {
        $eTime = [];

        for ($minutes = 30, $c = 100 * 60; $minutes <= $c; $minutes += 30) {
            $hour = floor($minutes / 60);
            $minute = $minutes % 60;
            $eTime[$minutes] = str_pad($hour, 2, '0', STR_PAD_LEFT) . ':'
                . str_pad($minute, 2, '0', STR_PAD_LEFT);
        }

        return $eTime;
    }

    /**
     * Get history tech statuses
     * @param $workOrderId
     *
     * @return mixed
     */
    public function techStatusHistory($workOrderId)
    {
        /** @var TechStatusHistoryRepository $techStatusHistoryRepository */
        $techStatusHistoryRepository = app(TechStatusHistoryRepository::class);
        
        return $techStatusHistoryRepository->getHistoryByWorkOrderId($workOrderId);
    }

    /**
     * Get work order by file
     *
     * @param  File  $file
     *
     * @return WorkOrder|null
     */
    public function getWorkOrderByFile(File $file)
    {
        switch ($file->getTableName()) {
            case 'time_sheet':
                return app(TimeSheetService::class)->getWorkOrderByTimeSheetId($file->getTableId());
        }

        return null;
    }
}
