<?php

namespace App\Modules\Address\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Address\Http\Requests\AddressRequest;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Models\AddressVerifyStatus;
use App\Modules\Address\Services\AddressGeocodingService;
use App\Modules\Contact\Models\ContactSql;
use App\Modules\Contact\Repositories\ContactRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Queue;

/**
 * Address repository class
 */
class AddressRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [
            'address_1',
            'address_2',
            'city',
            'county',
            'date_created',
            'state',
            'zip_code',
            'country',
            'address_name',
            'person_id',
            'is_default',
            'latitude',
            'longitude',
            'coords_accuracy',
            'geocoded',
            'user_geocoded',
            'geocoding_data',
            'verified',

            'has_default_vendor',
            'person_name',
        ];

    protected $sortableMap = [
        'date_created' => 'address.date_created',
    ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Address $address
     */
    public function __construct(Container $app, Address $address)
    {
        parent::__construct($app, $address);
    }

    public static function getAddressByPersonIds(int $personId)
    {
        return Address::select([
            'address.*',
            DB::raw('person_name(person_id) as person_name')
        ])
            ->where('person_id', $personId)
            ->orderBy('is_default', 'desc')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        $this->input = $this->request->all();

        $this->filterByCompany();

        $this->setModelDetails();

        $has_default_vendor = '(EXISTS (SELECT * FROM link_vendor_address lva WHERE lva.address_id = address.address_id))';
        $person_name = 'person_name(person_id)';

        $columns[] = "$person_name AS person_name";
        $columns[] = "$has_default_vendor AS has_default_vendor";

        /** @var Address|Object $model */
        $model = $this->getModel();
        $model = $model->isNotDeleted();

        $hasDefaultVendor = $this->request->query('has_default_vendor', '');
        if ($hasDefaultVendor != '') {
            $hasDefaultVendor = str_replace('%', '', $hasDefaultVendor);
            $model->whereRaw("$has_default_vendor=$hasDefaultVendor");

            unset($this->input['has_default_vendor']);
        }

        $personName = $this->request->query('person_name', '');
        if ($personName != '') {
            $model->whereRaw("$person_name LIKE '$personName'");

            unset($this->input['person_name']);
        }

        $model->selectRaw(implode(', ', $columns));

        $this->setWorkingModel($model);

        $result = parent::paginate($perPage, [], $order);

        [$zeroContacts, $zeroCompanies]
            = $this->addDataWithoutAddresses($result);

        $this->clearWorkingModel();
        $result = $result->toArray();
        if ($zeroContacts || $zeroCompanies) {
            $result['zero_contacts'] = $zeroContacts;
            $result['zero_companies'] = $zeroCompanies;
        }

        return $result;
    }

    protected function filterByCompany()
    {
        $companyOnly = $this->request->query('company_only', '');

        if ($companyOnly != '') {
            $model = $this->getModel();

            $model = $model
                ->has('company');

            $this->setWorkingModel($model);
        }
    }

    /**
     * Add relationships for detailed view (it is used on person page
     * where we list addresses and for each address we want also contacts
     * and relationships from link_person_company
     */
    protected function setModelDetails()
    {
        $detailed = $this->request->query('detailed', '');

        /** @var Address|Object $model */
        $model = $this->getModel();

        if ($detailed != '') {
            $model = $model->with('contactsWithType', 'personCompaniesWithDetails', 'personCompaniesWithDetails2');
        }

        $this->setWorkingModel($model);
    }

    /**
     * For detailed view if person is chosen as last item (on last page) we
     * need to add contacts for person not assigned to any address and
     * link to persons also not assigned to any address
     *
     * @param \Illuminate\Database\Eloquent\Collection|Paginator $paginator
     *
     * @return array
     */
    protected function addDataWithoutAddresses($paginator)
    {
        $detailed = $this->request->query('detailed', '');
        $person = (int)$this->request->query('person_id', 0);

        if ($detailed == '' || $person == 0 || $paginator->currentPage() > 1) {
            return [[], []];
        }

        /**
         * Creating fake (empty Address) model just to load relationship
         * and appends it as last element
         */
        /**
         * @var Address $address
         */
        $address = $this->newInstance();
        $address->address_id = 0;
        $address->date_created = '';
        $address->date_modified = '';
        $address->geocoding_data = '';

        /** Loading relationship - the same as in setModelDetails method but
         *  we need to add $person to filter records
         */
        $address->load(
            [
                'contactsWithType' => function ($q) use ($person) {
                    $q->where('person_id', $person);
                },
                'personCompaniesWithDetails' => function ($q) use ($person) {
                    $q->where('link_person_company.person_id', $person);
                },
                'personCompaniesWithDetails2' => function ($q) use ($person) {
                    $q->where('link_person_company.member_person_id', $person);
                },
            ]
        );

        /**
         * Add the fake model to results only if there are any data loaded
         * from relationships
         */
        if ($address->contactsWithType->count()
            || $address->personCompaniesWithDetails->count()
            || $address->personCompaniesWithDetails2->count()
        ) {
            $adr = $address->toArray();

            return [
                $adr['contacts_with_type'],
                $adr['person_companies_with_details'],
            ];
        }

        return [[], []];
    }

    /**
     * Creates and stores new Address object
     *
     * @param array $input
     *
     * @return array
     */
    public function create(array $input)
    {
        $input['geocoded'] = Address::NOT_GEOCODED;
        $input['coords_accuracy'] = '';
        $input['geocoding_data'] = '';
        $input['verified'] = AddressVerifyStatus::NOT_VERIFIED;

        if (!empty($input['latitude'])
            && !empty($input['longitude'])
        ) {
            $input['user_geocoded'] = 1;
        } else {
            $input['latitude'] = '';
            $input['longitude'] = '';
            $input['user_geocoded'] = 0;
        }

        [$input['is_default'], $changed]
            = $this->changeIsDefault($input['person_id'], $input['is_default']);

        $created = $this->model->create($input);

        $defChanged = [];

        if ($created) {
            $defChanged = $this->changeOtherDefaults(
                $input['person_id'],
                $input['is_default'],
                $created->address_id,
                $changed
            );
        }

        $jobOrder = $this->addToQueue($created);

        $queueData['job_order'] = $jobOrder;
        $queueData['job_queue'] = $this->getQueueName();

        return [$created, $queueData, $defChanged];
    }

    /**
     * Return correct is_default value for address
     *
     * @param int $personId
     * @param int $isDefault
     * @param int|null $exclude
     *
     * @return array
     */
    private function changeIsDefault($personId, $isDefault, $exclude = null)
    {
        $changed = false;

        if ($isDefault == 0) {
            $defaults = $this->model
                ->where('person_id', $personId)
                ->where('is_default', 1);
            if ($exclude != null) {
                $defaults->where('address_id', '<>', $exclude);
            }
            $defaults = $defaults->count();
            if ($defaults == 0) {
                $isDefault = 1;
                $changed = true;
            }
        }

        return [$isDefault, $changed];
    }

    /**
     * Sets other addresses as not default if necessary
     *
     * @param int $personId
     * @param int $isDefault
     * @param int $exclude
     * @param bool $changed
     *
     * @return array
     */
    private function changeOtherDefaults(
        $personId,
        $isDefault,
        $exclude,
        $changed
    ) {
        $notDef = [];

        if ($isDefault == 1 && $changed === false) {
            $defaults = $this->model->where('person_id', $personId)
                ->where('is_default', 1)
                ->where('address_id', '<>', $exclude)
                ->select('address_id', 'is_default')->get();

            foreach ($defaults as $def) {
                $record = [];
                $record['id'] = $def->address_id;
                $record['is_default'] = 0;
                $notDef[] = $record;
                $def->is_default = 0;
                $def->save();
            }
        }

        return $notDef;
    }

    /**
     * Adds address to geocoding and return record with queue data
     *
     * @param int $id
     *
     * @return array
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function verify($id)
    {
        $object = $this->find($id);
        $jobOrder = $this->addToQueue($object);

        $queueData['job_order'] = $jobOrder;
        $queueData['job_queue'] = $this->getQueueName();

        return [$object, $queueData];
    }

    /**
     * Updates Address object identified by given $id with $input data
     *
     * @param int $id
     * @param array $input
     *
     * @return array
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateWithIdAndInput($id, array $input)
    {
        /** @var Address $object */
        $object = $this->find($id);

        if ($input['latitude'] == '' || $input['longitude'] == '') {
            $input['latitude'] = '';
            $input['longitude'] = '';
            $input['user_geocoded'] = 0;
        } elseif ($object->getLatitude() != $input['latitude']
            || $object->getLongitude() != $input['longitude']
        ) {
            $input['user_geocoded'] = 1;
        } else {
            // restoring object data - we don't want use input here
            $input['user_geocoded'] = $object->getUserGeocoded();
        }

        /* Always clear this data - even if we don't do geocoding again
         * so we could make it again in future if we want to check address
         */

        $input['geocoded'] = Address::NOT_GEOCODED;
        $input['coords_accuracy'] = '';
        $input['geocoding_data'] = '';
        $input['verified'] = AddressVerifyStatus::NOT_VERIFIED;

        [$input['is_default'], $changed]
            = $this->changeIsDefault(
                $input['person_id'],
                $input['is_default'],
                $object->getId()
            );

        $status = $object->update($input);

        $defChanged = [];

        if ($status) {
            $defChanged = $this->changeOtherDefaults(
                $input['person_id'],
                $input['is_default'],
                $object->getId(),
                $changed
            );
        }

        $noGeocoding = $this->request->query('nogeocoding', '');

        $object = $this->find($id);

        $jobOrder = 0;
        if ($noGeocoding == '') {
            $jobOrder = $this->addToQueue($object);
        }

        $queueData = [];

        if ($jobOrder != 0) {
            $queueData['job_order'] = $jobOrder;
            $queueData['job_queue'] = $this->getQueueName();
        }

        return [$object, $queueData, $defChanged];
    }

    /**
     * Add object data to geocoding queue
     *
     * @param $object
     *
     * @return mixed
     */
    protected function addToQueue($object)
    {
        // at the moment we use general queue, instead of using $this->getQueueName()
        return Queue::push(AddressGeocodingService::class, $object->toArray());
    }

    /**
     * Update model without any formatting
     *
     * @param int $id
     * @param array $input
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function simpleUpdate($id, array $input)
    {
        $object = $this->find($id);

        if ($object) {
            /*$status = */
            $object->update($input);
        }
    }

    /**
     * Get queue name
     *
     * @return string
     */
    protected function getQueueName()
    {
        return '';
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new AddressRequest();

        return $req->getFrontendRules();
    }

    /**
     * Finds address for envelope printing and loads relationship if needed
     *
     * @param int $id
     *
     * @return mixed
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findForEnvelope($id)
    {
        $model = $this->model
            ->join('person', 'person.person_id', '=', 'address.person_id')
            ->selectRaw('person.custom_1, person.custom_3, person.kind, address.*')
            ->with('countryRel');

        $this->setWorkingModel($model);
        $record = parent::find($id);
        $this->clearWorkingModel();

        if ($record->kind == 'company') {
            $record->load([
                'personCompaniesWithDetails',
                'personCompaniesWithDetails2',
            ]);
        }

        return $record->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function show($id, $full = false)
    {
        $output['item'] = $this->find($id);

        if ($full) {
            $output['fields'] = $this->getConfig();
        }

        return $output;
    }

    /**
     * Get module configuration - request rules together with list od countries
     * and states
     *
     * @return array
     */
    public function getConfig()
    {
        $output = $this->getRequestRules();

        $cRep = \App::make(CountryRepository::class);

        $output['country']['data'] = $cRep->getList();

        $sRep = \App::make(StateRepository::class);

        $output['state']['data'] = $sRep->getList();

        return $output;
    }

    /**
     * Get addresses count for person with $personId id
     *
     * @param int $personId
     *
     * @return int
     */
    public function getCountForPerson($personId)
    {
        if (!$personId) {
            return 0; // not a valid person - 0 addresses
        }

        return $this->model->where('person_id', $personId)->count();
    }

    /**
     * Get addresses (or address) for given person
     *
     * @param int $personId
     * @param bool $first
     *
     * @return Collection|Address
     */
    public function getForPerson($personId, $first = false)
    {
        return $this->findForPerson($personId, $first);
    }

    /**
     * Get list of addresses or one address for person (and $addressId)
     * on WO create/update and display page
     *
     * @param int  $personId
     * @param int  $addressId
     * @param bool $onlyDefault
     *
     * @return mixed
     *
     */
    public function getForPersonWo($personId, $addressId = 0, $onlyDefault = false)
    {
        $contact = new ContactSql();

        $columns = [
            'address_id',
            'address_name',
            'address_1',
            'city',
            'state',
            'country',
            'zip_code',
            'is_default',
            $contact->getAddressPhoneSql('phone'),
            $contact->getAddressFaxSql('fax'),
        ];

        /** @var \Illuminate\Database\Query\Builder $model */
        $model = $this->model;

        if ($addressId) {
            $model = $model->where('address_id', $addressId);
        } else {
            $model = $model->where('person_id', $personId);
        }
        
        if ($onlyDefault) {
            $model = $model->where('is_default', 1);
        }
        
        $model =
            $model->selectRaw(implode(', ', $columns))->orderBy('address_name');

        if ($addressId || $onlyDefault) {
            return $model->first();
        }

        return $model->get();
    }

    /**
     * Get unverified addresses
     *
     * @param int $limit
     * @param string $sort
     *
     * @return Collection
     */
    public function getUnverified($limit = 10, $sort = 'address_id DESC')
    {
        return $this->model->where(
            'verified',
            AddressVerifyStatus::NOT_VERIFIED
        )->orderByRaw($sort)
            ->take($limit)->get();
    }

    /**
     * Update address verified status
     *
     * @param Address $address
     * @param         $status
     *
     * @return Address
     */
    public function updateVerifyStatus(Address $address, $status)
    {
        $address->verified = $status;
        $address->save();

        return $address;
    }

    /**
     * Search address by text
     *
     * @param string $searchKey
     * @param array $columns
     *
     * @return Collection|Paginator
     */
    public function searchByKey($searchKey, array $columns = ['address.*']) {
        $input = $this->getInput();
        
        /** @var Builder|Object|Address $model */
        $model = $this->getModel();

        if(empty($input['search_in'])) {
            $model = $model
                ->address1Contains($searchKey, true)
                ->address2Contains($searchKey, true);
            //->nameContains($searchKey, true);
        } else {
            $searchIn = explode(',', $input['search_in']);
            if(in_array('address1', $searchIn)) {
                $model = $model->address1Contains($searchKey, true);
            }

            if(in_array('address2', $searchIn)) {
                $model = $model->address2Contains($searchKey, true);
            }

            if(in_array('address_name', $searchIn)) {
                $model = $model->nameContains($searchKey, true);
            }
        }
        
        $this->setWorkingModel($model);

        $this->setRawColumns(true);

        $data = parent::paginate(50, $columns, []);

        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Search address by text
     *
     * @param string $searchKey
     * @param array $columns
     *
     * @return Collection|Paginator
     */
    public function searchByAddressName($searchKey, array $columns = ['address.*']) {

        /** @var Builder|Object|Address $model */
        $model = $this->getModel();

        $model = $model->nameContains($searchKey, true);

        $this->setWorkingModel($model);

        $this->setRawColumns(true);

        $data = parent::paginate(50, $columns, []);

        $this->clearWorkingModel();

        return $data;
    }
    
    /**
     * Removes given Address object
     *
     * @param array|int $id
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function markAsDeleted($id)
    {
        /** @var Address $address */
        $address = $this->find($id);

        DB::beginTransaction();

        try {
            $address->setIsDeleted(true);
            $address->save();

            $contactRepository = $this->app->make(ContactRepository::class);
            $contactRepository->markAsDeletedByAddress($id);

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();

            throw $ex;
        }

        return true;
    }

    /**
     * Get places to save via Fleetmatics API
     *
     * @param int $itemsCount - count of item, as default: 50
     * @return Collection
     */
    public function getNewPlaces($itemsCount = 50)
    {
        //Get items with empty external address id or item where date modified is greater than last update external address
        $query = Address::select('address.*')
            ->whereRaw('(address.external_unable_to_resolve IS NULL OR address.external_unable_to_resolve = 0) AND (address.external_address_id IS NULL OR (address.external_address_id IS NOT NULL AND DATE(address.date_external_updated) < DATE(address.date_modified)))');

        //In MGM we use truck orders instead of work orders
        if (config('app.crm_user') == 'mgm') {
            $query = $query->join('link_address_truck_order as la', 'la.address_id', '=', 'address.address_id');
        } else {
            $query = $query->join('work_order as wo', 'wo.shop_address_id', '=', 'address.address_id');
        }

        $data = $query->groupBy('address.address_id')
            ->take($itemsCount)
            ->get();

        return $data;
    }

    /**
     * Get main address for company (address_name = 'Main Office')
     *
     * @param integer $personId - person id
     * @return null|Address
     */
    public function getMainOficeAddress($personId)
    {
        return Address::where('person_id', '=', $personId)
            ->whereRaw("UPPER(address_name) like '%MAIN OFFICE%'")
            ->first();
    }

    /**
     * Get default address for company
     *
     * @param integer $personId - person id
     * @return null|Address
     */
    public function getDefaultForPerson($personId)
    {
        return $this->model::where('person_id', '=', $personId)
            ->where('is_default', 1)
            ->first();
    }

    /**
     * @param $companyId
     *
     * @return int
     */
    public function getCompanyDefaultAddress($companyId): int
    {
        return $this->model::where('person_id', '=', $companyId)
            ->orderByDesc('is_default')
            ->value('address_id');
    }

    /**
     * @param  array  $addressNames
     *
     * @return mixed
     */
    public function getAddressIdsByAddressNames(array $addressNames)
    {
        if (!$addressNames) {
            return [];
        }
        
        return $this->model
            ->whereIn('address_name', $addressNames)
            ->pluck('address_id', 'address_name')
            ->all();
    }

    public function getAddressesByWorkOrderNumbers(array $workOrderNumbers)
    {
        if (!$workOrderNumbers) {
            return [];
        }
        
        $mappedAddresses = [];
        
        $addresses = DB::table('work_order')
            ->select('work_order_number', 'address_id', 'person_id', 'address_1', 'address_2', 'city', 'country', 'state', 'zip_code', 'address_name')
            ->join('address', 'work_order.shop_address_id', '=', 'address.address_id')
            ->whereIn('work_order_number', $workOrderNumbers)
            ->get();
        
        foreach ($addresses as $address) {
            $mappedAddresses[$address->work_order_number] = $address;
            
            unset($mappedAddresses[$address->work_order_number]->work_order_number);
        }
        
        return $mappedAddresses;
    }
}
