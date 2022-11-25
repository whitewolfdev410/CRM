<?php

namespace App\Modules\Person\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Person\Models\BillingCompanySetting;
use Illuminate\Container\Container;

/**
 * Company repository class
 */
class BillingCompanySettingsRepository extends AbstractRepository
{
    protected $selectedKind;

    /**
     * Repository constructor
     *
     * @param Container             $app
     * @param BillingCompanySetting $billingCompanySetting
     */
    public function __construct(Container $app, BillingCompanySetting $billingCompanySetting)
    {
        parent::__construct($app, $billingCompanySetting);
    }

    /**
     * Check if company has billing company
     *
     * @param int $companyId
     * @param int $billingCompanyId
     *
     * @return bool
     */
    public function checkIfCompanyHasBillingCompany($companyId, $billingCompanyId)
    {
        return (bool)$this->model->where('company_id', $companyId)
            ->where('billing_company_id', $billingCompanyId)
            ->where('pricing_structure_id', '>', 0)
            ->first();
    }
}
