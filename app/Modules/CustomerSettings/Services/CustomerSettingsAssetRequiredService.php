<?php

namespace App\Modules\CustomerSettings\Services;

use App\Modules\Type\Models\Type;
use Carbon\Carbon;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Exception;

class CustomerSettingsAssetRequiredService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * CustomerSettingsService constructor.
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @return array
     */
    public function getAssetRequiredTypes()
    {
        $key = 'customer-settings-form-types';
        $cachedData = Cache::get($key);

        if ($cachedData) {
            //prepare response
            $expiresAt = $cachedData['expiresAt'];
            $types     = $cachedData['types'];
        } else {
            $expiresAtDate = Carbon::now()->addMinutes(60);
            $expiresAt = $expiresAtDate->toW3cString();

            $types = $this->getRequiredTypes();

            $cachedData = [
                'types' => $types,
                'expiresAt'   => $expiresAt,
            ];

            Cache::add($key, $cachedData, 3600);
        }

        return [
            'types'      => $types,
            'expired_at' => $expiresAt,
        ];
    }

    /**
     * Get all required types
     * @return array
     */
    private function getRequiredTypes()
    {
        $types = [];

        $types['asset_required'] = $this->getTypesByType('asset_required');

        return $types;
    }


    /**
     * @param string $type
     * @return mixed
     */
    private function getTypesByType($type)
    {
        return Type::where('type', '=', $type)
            ->pluck('type_value', 'type_id')
            ->all();
    }


    /**
     * @param int $customerSettingsId
     * @return mixed
     * @throws Exception
     */
    public function saveAssetRequired($customerSettingsId)
    {
        $data = request()->all();

        $assetSystemTypeId = null;
        if (isset($data['asset_system_type_id'])) {
            $assetSystemTypeId = $data['asset_system_type_id'];
        }

        $values = [
            'customer_settings_id' => $customerSettingsId,
            'asset_system_type_id' => $assetSystemTypeId,
            'asset_required_type_id' => $data['asset_required_type_id'],
            'created_at' => Carbon::now('UTC')->format('Y-m-d H:i:s')
        ];

        if ($this->getAssetRequiredSettings($customerSettingsId, $assetSystemTypeId, $data['asset_required_type_id'])) {
            throw new Exception('Already exists.');
        }

        if (is_null($assetSystemTypeId)) {
            $this->removeAssetRequiredSpecifiedAssetType($customerSettingsId, $data['asset_required_type_id']);
        } else {
            $this->removeAssetRequiredForGlobalAssetTypes($customerSettingsId, $data['asset_required_type_id']);
        }

        return $this->app['db']
            ->table('asset_required')
            ->insert($values);
    }

    public function deleteAssetRequired($customerSettingsId)
    {
        $data = request()->all();

        $assetSystemTypeId = null;
        if (isset($data['asset_system_type_id'])) {
            $assetSystemTypeId = $data['asset_system_type_id'];
        }

        $query = $this->app['db']
            ->table('asset_required')
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('asset_required_type_id', '=', $data['asset_required_type_id']);
        if (is_null($assetSystemTypeId)) {
            $query->whereNull('asset_system_type_id');
        } else {
            $query->where('asset_system_type_id', '=', $assetSystemTypeId);
        }

        return $query->delete();
    }

    /**
     * @param int $customerSettingsId
     * @param int $assetTypeId
     * @param int $requiredTypeId
     * @return mixed
     */
    private function getAssetRequiredSettings($customerSettingsId, $assetTypeId, $requiredTypeId)
    {
        $query = $this->app['db']
            ->table('asset_required')
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('asset_required_type_id', '=', $requiredTypeId);
        if (is_null($assetTypeId)) {
            $query->whereNull('asset_system_type_id');
        } else {
            $query->where('asset_system_type_id', '=', $assetTypeId);
        }

        return $query->first();
    }

    /**
     * @param int $customerSettingsId
     * @param int $requiredTypeId
     */
    private function removeAssetRequiredSpecifiedAssetType($customerSettingsId, $requiredTypeId)
    {
        $this->app['db']
            ->table('asset_required')
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('asset_required_type_id', '=', $requiredTypeId)
            ->whereNotNull('asset_system_type_id')
            ->delete();
    }

    /**
     * @param int $customerSettingsId
     * @param int $requiredTypeId
     */
    private function removeAssetRequiredForGlobalAssetTypes($customerSettingsId, $requiredTypeId)
    {
        $this->app['db']
            ->table('asset_required')
            ->where('customer_settings_id', '=', $customerSettingsId)
            ->where('asset_required_type_id', '=', $requiredTypeId)
            ->whereNull('asset_system_type_id')
            ->delete();
    }
}
