<?php

namespace App\Modules\CustomerSettings\Repositories;

use App\Core\AbstractRepository;
use App\Modules\CustomerSettings\Models\CustomerSettingsItem;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * CustomerSettings repository class
 */
class CustomerSettingsItemsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container            $app
     * @param CustomerSettingsItem $customerSettingsItem
     */
    public function __construct(
        Container $app,
        CustomerSettingsItem $customerSettingsItem
    ) {
        parent::__construct($app, $customerSettingsItem);
    }

    /**
     * Get settings item by key and customer settings id
     *
     * @param      $key
     * @param      $customerSettingsId
     * @param null $default
     *
     * @return |null
     */
    public function get($key, $customerSettingsId, $default = null)
    {
        $result = $this->model
            ->where('customer_settings_id', $customerSettingsId)
            ->where('key', $key)
            ->first();

        if ($result) {
            return $result;
        }

        return $default;
    }

    /**
     * Get all settings by customer settings id
     *
     * @param $customerSettingsId
     *
     * @return mixed
     */
    public function getByCustomerSettingsId($customerSettingsId)
    {
        return $this->model
            ->where('customer_settings_id', $customerSettingsId)
            ->get();
    }

    /**
     * @param $customerSettingsId
     *
     * @return mixed
     */
    public function getListByCustomerSettingsId($customerSettingsId)
    {
        return $this->model
            ->where('customer_settings_id', $customerSettingsId)
            ->pluck('value', 'key')
            ->all();
    }
    
    /**
     * Set new value for key
     *
     * @param $key
     * @param $customerSettingsId
     * @param $value
     *
     * @return mixed
     */
    public function set($key, $customerSettingsId, $value)
    {
        return $this->model->updateOrCreate([
            'key'                  => $key,
            'customer_settings_id' => $customerSettingsId
        ], [
            'value' => $value
        ]);
    }

    /**
     * Set new value by id
     *
     * @param $id
     * @param $value
     *
     * @return bool
     */
    public function setById($id, $value)
    {
        $customerSettingsItem = $this->find($id);
        $customerSettingsItem->value = $value;

        return $customerSettingsItem->save();
    }

    /**
     * Get customer settings item by customer setting id and key or if is not found, return a new model instance
     *
     * @param $customerSettingsId
     * @param $key
     *
     * @return mixed
     */
    public function findByCustomerSettingsIdAndKey($customerSettingsId, $key)
    {
        return $this->model->firstOrNew([
            'customer_settings_id' => $customerSettingsId,
            'key'                  => $key
        ]);
    }

    /**
     * Get setting key with ids by customerSettingIds
     *
     * @param $customerSettingIds
     *
     * @return mixed
     */
    public function getKeyWithIdsByCustomerSettingIds($customerSettingIds)
    {
        return $this->model
            ->whereIn('customer_settings_id', $customerSettingIds)
            ->pluck('key', 'id')
            ->all();
    }

    /**
     * @param array $companyPersonIds
     *
     * @return string|null
     */
    public function getLocationClientChangeDate(array $companyPersonIds)
    {
        $customerSettingsItem = $this->model
            ->select([
                DB::raw('max(value) as value')
            ])
            ->join('customer_settings', function ($join) use ($companyPersonIds) {
                $join
                ->on('customer_settings.customer_settings_id', '=', 'customer_settings_items.customer_settings_id')
                    ->whereIn('customer_settings.company_person_id', $companyPersonIds);
            })
            ->where('customer_settings_items.key', 'assets.location_client_change_date')
            ->whereNotNull('customer_settings_items.value')
            ->where('customer_settings_items.value', '<>', '')
            ->first();
        
        if ($customerSettingsItem && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $customerSettingsItem->value)) {
            return $customerSettingsItem->value;
        }
        
        return null;
    }
}
