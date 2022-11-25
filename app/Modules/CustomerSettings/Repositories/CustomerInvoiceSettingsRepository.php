<?php

namespace App\Modules\CustomerSettings\Repositories;

use App\Core\AbstractRepository;
use App\Modules\CustomerSettings\Models\CustomerInvoiceSettings;
use Illuminate\Container\Container;

/**
 * CustomerSettings repository class
 */
class CustomerInvoiceSettingsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container               $app
     * @param CustomerInvoiceSettings $customerInvoiceSettings
     */
    public function __construct(
        Container $app,
        CustomerInvoiceSettings $customerInvoiceSettings
    ) {
        parent::__construct($app, $customerInvoiceSettings);
    }

    /**
     * Get communication system value
     *
     * @param $companyPersonId
     *
     * @return string|null
     */
    public function getCommunicationSystemValue($companyPersonId)
    {
        $communicationSystem = null;

        $customerInvoiceSettings = $this->model->where('company_person_id', $companyPersonId)->first();
        if ($customerInvoiceSettings && (int)$customerInvoiceSettings->active === 1) {
            switch ($customerInvoiceSettings->delivery_method) {
                case 'email':
                    $communicationSystem = 'Email';
                    break;
                case 'mail':
                    $communicationSystem = 'Lob';
                    break;
            }
        }

        return $communicationSystem;
    }

    /**
     * Get customer invoice settings by company_person_id or if is not found, return a new model instance
     *
     * @param $companyPersonId
     *
     * @return mixed
     */
    public function findByCompanyPersonId($companyPersonId)
    {
        return $this->model->firstOrNew(['company_person_id' => $companyPersonId]);
    }
}
