<?php

namespace App\Modules\Person\Repositories;

use App\Modules\Person\Models\Company;
use Illuminate\Container\Container;

/**
 * Employee repository class
 */
class EmployeeRepository extends PersonRepository
{
    protected $selectedKind;

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Company $company
     * @param string $kind
     */
    public function __construct(
        Container $app,
        Company $company,
        $kind = 'person'
    ) {
        parent::__construct($app, $company, $kind);

        $this->datasetClass = 'EmployeeDataset';
    }
}
