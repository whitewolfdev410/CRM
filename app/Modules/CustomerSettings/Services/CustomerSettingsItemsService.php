<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\CustomerSettings\Http\Requests\UpdateCustomerSettingsItemsRequest;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsItemsRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsOptionsRepository;
use App\Modules\CustomerSettings\Repositories\CustomerSettingsRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Container\Container;

class CustomerSettingsItemsService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var CustomerSettingsRepository
     */
    private $customerSettingsRepository;

    /**
     * @var CustomerSettingsItemsRepository
     */
    private $customerSettingsItemsRepository;

    /**
     * @var CustomerSettingsOptionsRepository
     */
    private $customerSettingsOptionsRepository;

    /**
     * CustomerSettingsService constructor.
     *
     * @param Container                         $app
     * @param CustomerSettingsRepository        $customerSettingsRepository
     * @param CustomerSettingsItemsRepository   $customerSettingsItemsRepository
     * @param CustomerSettingsOptionsRepository $customerSettingsOptionsRepository
     */
    public function __construct(
        Container $app,
        CustomerSettingsRepository $customerSettingsRepository,
        CustomerSettingsItemsRepository $customerSettingsItemsRepository,
        CustomerSettingsOptionsRepository $customerSettingsOptionsRepository
    ) {
        $this->app = $app;
        $this->customerSettingsRepository = $customerSettingsRepository;
        $this->customerSettingsItemsRepository = $customerSettingsItemsRepository;
        $this->customerSettingsOptionsRepository = $customerSettingsOptionsRepository;
    }

    /**
     * Get settings item by key and customer settings id
     *
     * @param      $key
     * @param int  $customerSettingsId
     * @param null $default
     *
     * @return mixed
     */
    public function get($key, $customerSettingsId, $default = null)
    {
        return $this->customerSettingsItemsRepository->get($key, $customerSettingsId, $default);
    }

    /**
     * Get settings list by customer settings id
     *
     * @param $customerSettingsId
     *
     * @return array
     */
    public function getListByCustomerSettingsId($customerSettingsId)
    {
        return $this->customerSettingsItemsRepository->getListByCustomerSettingsId($customerSettingsId);
    }

    /**
     * Get value by key and customer settings id
     *
     * @param      $key
     * @param int  $customerSettingsId
     * @param null $default
     *
     * @return string|null
     */
    public function getValue($key, $customerSettingsId, $default = null)
    {
        $result = $this->customerSettingsItemsRepository->get($key, $customerSettingsId, false);

        if ($result) {
            return $result->value;
        }

        return $default;
    }

    /**
     * Set new value for key
     *
     * @param string $key
     * @param int    $customerSettingsId
     * @param string $value
     *
     * @return mixed
     */
    public function set($key, $customerSettingsId, $value)
    {
        return $this->customerSettingsItemsRepository->set($key, $customerSettingsId, $value);
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
        return $this->customerSettingsItemsRepository->setById($id, $value);
    }

    /**
     * Get customer settings
     *
     * @param int $customerSettingsId
     *
     * @return array
     */
    public function show($customerSettingsId)
    {
        $options = $this->customerSettingsOptionsRepository->getAllOptions();
        $settings = $this->customerSettingsItemsRepository->getByCustomerSettingsId($customerSettingsId);

        return [
            'items' => $this->parseOptionsAndSettings($options, $settings)
        ];
    }

    /**
     * Update customer settings
     *
     * @param                                    $customerSettingsId
     * @param UpdateCustomerSettingsItemsRequest $request
     *
     * @return bool
     * @throws \Exception
     */
    public function update($customerSettingsId, UpdateCustomerSettingsItemsRequest $request)
    {
        $settingsToUpdate = $request->get('settings', []);

        DB::beginTransaction();

        try {
            foreach ($settingsToUpdate as $item) {
                $customerSettingsItem = $this->customerSettingsItemsRepository->findByCustomerSettingsIdAndKey(
                    $customerSettingsId,
                    $item['name']
                );

                if ($item['value'] === false) {
                    $item['value'] = 0;
                }

                if ($item['value'] === true) {
                    $item['value'] = 1;
                }
                
                $customerSettingsItem->value = $item['value'];
                $customerSettingsItem->save();
            }

            DB::commit();

            return true;
        } catch (\Exception $ex) {
            DB::rollback();

            throw $ex;
        }
    }

    /**
     * Parse and merge options and settings values
     *
     * @param $options
     * @param $settings
     *
     * @return array
     */
    private function parseOptionsAndSettings(&$options, &$settings)
    {
        $settingsByKey = [];
        foreach ($settings as $item) {
            $settingsByKey[$item->key] = [
                'id'    => $item->id,
                'value' => $item->value
            ];
        }

        $data = [];
        if ($options) {
            foreach ($options as $option) {
                $item = [
                    "id"      => null,
                    "name"    => $option->key,
                    "label"   => $option->label,
                    "value"   => null,
                    "type"    => $option->type,
                    "options" => $this->parseOptions($option->options),
                ];

                if (isset($settingsByKey[$option->key])) {
                    $item = array_merge($item, $settingsByKey[$option->key]);

                    if ($option->type === 'checkbox') {
                        $item['value'] = (bool)$item['value'];
                    }
                }

                $data[] = $item;
            }
        }

        return $data;
    }

    /**
     * Parse options object
     *
     * @param $options
     *
     * @return array|null
     */
    private function parseOptions($options)
    {
        $options = json_decode($options);
        if ($options) {
            $data = [];
            foreach ($options as $value => $label) {
                $data[] = [
                    'label' => $label,
                    'value' => $value
                ];
            }

            return $data;
        }

        return null;
    }

    /**
     * @param $companyPersonIds
     *
     * @return string|null
     */
    public function getLocationClientChangeDate($companyPersonIds)
    {
        if (!is_array($companyPersonIds)) {
            $companyPersonIds = [$companyPersonIds];
        }
        
        return $this->customerSettingsItemsRepository->getLocationClientChangeDate($companyPersonIds);
    }
}
