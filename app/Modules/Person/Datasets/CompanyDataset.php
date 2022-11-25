<?php

namespace App\Modules\Person\Datasets;

use App\Modules\Contact\Models\Contact;
use App\Modules\Contact\Repositories\ContactRepository;

class CompanyDataset extends PersonDataset
{
    /**
     * Get type_id data
     *
     * @return mixed
     */
    public function getTypeIdData()
    {
        return $this->typeRepository->getList('company');
    }
}
