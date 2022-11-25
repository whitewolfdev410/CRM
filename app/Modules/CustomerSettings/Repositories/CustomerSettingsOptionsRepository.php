<?php

namespace App\Modules\CustomerSettings\Repositories;

use App\Core\AbstractRepository;
use App\Modules\CustomerSettings\Models\CustomerSettingsOption;
use Illuminate\Container\Container;

/**
 * CustomerSettingsOptions repository class
 */
class CustomerSettingsOptionsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container              $app
     * @param CustomerSettingsOption $customerSettingsOption
     */
    public function __construct(
        Container $app,
        CustomerSettingsOption $customerSettingsOption
    ) {
        parent::__construct($app, $customerSettingsOption);
    }

    /**
     * Get all settings options
     *
     * @return mixed
     */
    public function getAllOptions()
    {
        return $this->model->get();
    }

    /**
     * Get pair key with label
     */
    public function getLabels()
    {
        return $this->model
            ->pluck('label', 'key')
            ->all();
    }
}
