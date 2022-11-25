<?php

namespace App\Modules\Person\Repositories;

use App\Modules\Person\Models\Company;
use App\Modules\Person\Models\Person;
use Illuminate\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Company repository class
 */
class CompanyRepository extends PersonRepository
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
        $kind = 'company'
    ) {
        parent::__construct($app, $company, $kind);
    }

    /**
     * @param $id
     * @return Person|Builder|\Illuminate\Support\Collection
     */
    public function getClientPortalPersons(
        $id
    ) {
        /** @var Person|Builder $persons */
        $persons = new Person();

        /** @var Person|Builder $clientPortalUser */
        $clientPortalUser = new Person();
        $clientPortalUser = $clientPortalUser->find($id);

        $company_person_id = $clientPortalUser->custom_8;


        /** @var Builder $sl_customer */
        $sl_customer = DB::table('sl_records')
            ->where('sl_table_name', 'Customer')
            ->where('record_id', $company_person_id)->first();

        $probably_customers_ids = [];
        $probably_customers_ids[] = $company_person_id;

        $result = preg_replace("/[^A-Z]+/", "", $sl_customer->sl_record_id) . '0';

        $probably_customers = DB::table('sl_records')
            ->where('sl_table_name', 'Customer')
            ->where('sl_record_id', 'LIKE', $result . '%')
            ->where('record_id', '!=', $id)->get();

        foreach ($probably_customers as $customer) {
            $probably_customers_ids[] = $customer->record_id;
        }


        $persons = $persons->whereIn('custom_8', $probably_customers_ids)
            ->leftJoin('users', 'person.person_id', '=', 'users.person_id')
            ->leftJoin('link_person_company', function ($join) use ($id) {
                /** @var JoinClause $join */
                $join->on('link_person_company.person_id', '=', 'person.person_id');
                $join->where('link_person_company.member_person_id', '=', $id);
            })->select(['person.person_id as person_id',
                'person.custom_3 as person_name',
                //'users.id as user_id',
                'users.email as email',
                'link_person_company.link_person_company_id as link_person_company_id'
                ])->get();

        return $persons;
    }

    /**
     * Get all data - fields, labels, validation rules (without menu)
     *
     * @param $action
     *
     * @return array
     */
    public function getDatasetData($action)
    {
        $dataset = $this->getDataset();
        $data['fields'] = $dataset->getData($action);
        $data['groups']['data'] = $this->getPersonGroups();

        return $data;
    }
    
    /**
     * Get group types from type table that will be used
     *
     * @return array|string
     */
    protected function getGroupsType()
    {
        return [
            'Current' => 'company_current_services',
            'Market' => 'company_market_services',
            'Trade' => 'company_trade',
            'Category' => 'company_category',
        ];
    }
}
