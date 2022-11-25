<?php

namespace App\Modules\Person\Datasets;

use App\Modules\Contact\Models\Contact;
use App\Modules\Contact\Repositories\ContactRepository;

class CrmAppCompanyDataset extends CompanyDataset
{
    /**
     * Sample method to show how to create custom dropdown
     *
     * @return mixed
     */
    public function getCustom1Data()
    {
        $addressRepository = new ContactRepository($this->app, new Contact());

        return $addressRepository->pluck('name', 'contact_id');
    }

    /**
     * Sample method to show how to get custom field value (if it's connected
     * to other table) when getting single record
     *
     * @return array
     */
    public function getCustom1DetailedData()
    {
        return [
            'join'    => [
                'contact',
                'person.custom_1',
                '=',
                'contact.contact_id',
            ],
            'columns' => ['contact.name AS `custom_1_value`'],
        ];
    }

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
