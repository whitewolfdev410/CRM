<?php

namespace App\Modules\CustomerSettings\Repositories;

use App\Core\AbstractRepository;
use App\Modules\CustomerSettings\Http\Requests\CustomerSettingsRequest;
use App\Modules\CustomerSettings\Models\CustomerSettings;
use App\Modules\WorkOrder\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * CustomerSettings repository class
 */
class CustomerSettingsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container        $app
     * @param CustomerSettings $customerSettings
     */
    public function __construct(
        Container $app,
        CustomerSettings $customerSettings
    ) {
        parent::__construct($app, $customerSettings);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new CustomerSettingsRequest();

        return $req->getFrontendRules();
    }

    /**
     * Get list of ids that are assigned to $personId
     *
     * @param $personId
     *
     * @return array
     */
    public function getIds($personId)
    {
        return $this->model->select('customer_settings_id')
            ->where(
                'company_person_id',
                $personId
            )
            ->pluck('customer_settings_id')
            ->all();
    }

    /**
     * Get meta_data field by work order ID
     *
     * @param $workOrderId
     *
     * @return string
     */
    public function getMetaDataByWorkOrderId($workOrderId)
    {
        $settings = $this->model->select('meta_data')
            ->join(
                'work_order',
                'work_order.company_person_id',
                '=',
                'customer_settings.company_person_id'
            )
            ->where('work_order.work_order_id', $workOrderId)
            ->first();


        if ($settings) {
            return json_decode($settings->getMetaData(), true);
        }

        return null;
    }

    /**
     * Get meta_data field by work order ID
     *
     * @param array $workOrderIds
     * @param array $columns
     *
     * @return string
     */
    public function getSettingsByWorkOrderIds($workOrderIds, $columns = ['work_order_id, customer_settings.*'])
    {
        return $this->model->select($columns)
            ->join(
                'work_order',
                'work_order.company_person_id',
                '=',
                'customer_settings.company_person_id'
            )
            ->whereIn('work_order.work_order_id', $workOrderIds)
            ->get();
    }

    /**
     * Get meta_data field for person
     *
     * @param int  $personId
     * @param bool $notNull
     *
     * @return string
     */
    public function getMetaDataForPerson($personId, $notNull = false)
    {
        $model = $this->model->where('company_person_id', $personId);

        if ($notNull) {
            $model = $model->whereNotNull('meta_data');
        }

        $rec = $model->first();

        if ($rec) {
            return $rec->meta_data;
        }

        return null;
    }

    /**
     * Get first customer settings for given person
     *
     * @param int $personId
     *
     * @return CustomerSettings
     */
    public function getForPerson($personId)
    {
        return $this->model->where('company_person_id', $personId)->first();
    }

    /**
     * Get due date based on created date and customer settings.
     *
     * @param WorkOrder $wo
     * @param Carbon    $createdDate
     *
     * @return Carbon
     */
    public function getDueDate(WorkOrder $wo, Carbon $createdDate)
    {
        $createdDate = $createdDate->copy();
        if (!$wo) {
            return $createdDate;
        }

        /** @var CustomerSettings $settings */
        $settings = $this->model->where(
            'company_person_id',
            $wo->getCompanyPersonId()
        )->first();

        if (!$settings || empty($settings->getMetaData())) {
            return $createdDate;
        }

        $customerSettings = json_decode($settings->getMetaData(), true);

        if (!empty($customerSettings['Payment_terms']['answer'])) {
            switch ($customerSettings['Payment_terms']['answer']) {
                case 'Net in 60 Days':
                    return $createdDate->addDays(60);
                    break;
                case 'Net in 45 Days':
                    return $createdDate->addDays(45);
                    break;
                case 'Net in 30 Days':
                    return $createdDate->addDays(30);
                    break;
                case 'Payable in Advance':
                case 'Due on Receipt':
                default:
                    return $createdDate->addDays(30);
                    break;
            }
        }

        return $createdDate;
    }


    /**
     * Get first person with customer settings for given customer Id
     *
     * @param string $customerId
     *
     * @return Model
     */
    public function getPersonForCustomerId($customerId)
    {
        $result = $this->model
            ->select('*')
            ->selectRaw('person_name(person.person_id) AS person_name')
            ->join('person', 'person_id', '=', 'company_person_id')
            ->join('sl_records', function ($join) use ($customerId) {
                $join
                    ->on('sl_records.record_id', '=', 'person.person_id')
                    ->where('sl_table_name', '=', 'Customer')
                    ->where('sl_record_id', '=', $customerId);
            })
            ->first();

        $result->person_name = "{$result->person_name} ({$result->sl_record_id})";

        return $result;
    }

    /**
     * Get first person with customer settings for given person Id
     *
     * @param int $personId
     *
     * @return Model
     */
    public function getPersonForPersonId($personId)
    {
        $query = $this->model
            ->select('*')
            ->selectRaw('person_name(person.person_id) AS person_name')
            ->join('person', 'person_id', '=', 'company_person_id')
            ->where('company_person_id', $personId);

        // append SL Customer ID for BFC
        if ($this->app->crm->is('bfc')) {
            $query->leftJoin('sl_records', function ($j) {
                $j
                    ->on('sl_records.record_id', '=', 'person.person_id')
                    ->where('sl_table_name', '=', 'Customer');
            });

            $result = $query->first();

            if ($result && $result->sl_record_id) {
                $result->person_name = "{$result->person_name} ({$result->sl_record_id})";
            }
        } else {
            $result = $query->first();
        }

        return $result;
    }

    /**
     * Get communication system form bfc_crm_inv database
     *
     * @param $company_person_id
     *
     * @return bool
     */
    public function getCommunicationSystemFromBfcInv($company_person_id)
    {
        $customerSettings = $this->getCustomerSettingsFromBfcInv($company_person_id);
        if (!empty($customerSettings)) {
            $metaData = json_decode($customerSettings->meta_data, true);

            if (isset($metaData['Communication_system?']['answer'])) {
                return $metaData['Communication_system?']['answer'];
            }
        }

        return false;
    }

    /**
     * Update communication system in bfc_crm_inv database
     *
     * @param $company_person_id
     * @param $value
     *
     * @return bool
     */
    public function updateCommunicationSystemFromBfcInv($company_person_id, $value)
    {
        $customerSettings = $this->getCustomerSettingsFromBfcInv($company_person_id);
        if (!empty($customerSettings)) {
            $metaData = json_decode($customerSettings->meta_data, true);

            if (!$metaData) {
                $metaData = [];
            }

            $metaData['Communication_system?']['answer'] = $value;

            DB::update('
                update 
                    bfc_crm_inv.customer_settings 
                set 
                    meta_data = ? 
                where 
                    customer_settings_id = ?
            ', [json_encode($metaData), $customerSettings->customer_settings_id]);
        }

        return false;
    }
    
    /**
     * Get customer settings form bfc_crm_inv based company_person_id from bfc_crm
     *
     * @param $company_person_id
     *
     * @return bool
     */
    private function getCustomerSettingsFromBfcInv($company_person_id)
    {
        $slRecord = DB::table('sl_records')
            ->where('sl_table_name', DB::raw("'Customer'"))
            ->where('record_id', $company_person_id)
            ->first();

        if ($slRecord) {
            $customerSettings = DB::select('
                select
                    customer_settings_id, meta_data
                from
                    bfc_crm_inv.customer_settings
                inner join
                    bfc_crm_inv.sl_records on sl_records.record_id = customer_settings.company_person_id
                where
                    sl_records.sl_record_id = ? and
                    sl_records.sl_table_name = "Customer"
                limit
                    1
            ', [
                $slRecord->sl_record_id
            ]);

            if (isset($customerSettings[0])) {
                return $customerSettings[0];
            }
        }


        return false;
    }
}
