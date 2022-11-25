<?php

namespace App\Modules\Person\Datasets;

class EmployeeDataset extends PersonDataset
{
    /**
     * Get type_id data
     *
     * @return mixed
     */
    public function getTypeIdData()
    {
        return $this->typeRepository->getAllByKey('person.employee');
    }
}
