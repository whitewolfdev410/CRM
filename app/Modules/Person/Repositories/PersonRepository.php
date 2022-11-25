<?php

namespace App\Modules\Person\Repositories;

use App\Core\AbstractRepository;
use App\Core\User;
use App\Modules\Address\Repositories\AddressIssueRepository;
use App\Modules\Contact\Models\ContactSql;
use App\Modules\CreditCard\Repositories\CreditCardTransactionRepository;
use App\Modules\File\Repositories\FileRepository;
use App\Modules\File\Services\FileService;
use App\Modules\LastCheckOut\Repositories\LastCheckOutRepository;
use App\Modules\MsDynamics\Services\MsDynamicsService;
use App\Modules\Person\Models\Company;
use App\Modules\Person\Models\Person;
use App\Modules\Receipt\Repositories\ReceiptRepository;
use App\Modules\TimeSheet\Repositories\TimeSheetRepository;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\User\Repositories\UserRepository;
use App\Modules\User\Services\ClientPortalUserService;
use App\Modules\User\Services\UserService;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Person repository class
 */
class PersonRepository extends AbstractRepository
{
    /**
     * Kind of Person (column kind in person table in database)
     *
     * @var string
     */
    protected $selectedKind;

    /**
     * Dataset class name that would be used to create datasets
     *
     * @var mixed
     */
    protected $datasetClass;

    protected $availableColumns = [
        'person_id'                   => 'person.person_id',
        'id'                          => 'person.person_id',
        'custom_1'                    => 'person.custom_1',
        'custom_2'                    => 'person.custom_2',
        'custom_3'                    => 'person.custom_3',
        'custom_4'                    => 'person.custom_4',
        'custom_5'                    => 'person.custom_5',
        'custom_6'                    => 'person.custom_6',
        'custom_7'                    => 'person.custom_7',
        'custom_8'                    => 'person.custom_8',
        'custom_9'                    => 'person.custom_9',
        'custom_10'                   => 'person.custom_10',
        'custom_11'                   => 'person.custom_11',
        'custom_12'                   => 'person.custom_12',
        'custom_13'                   => 'person.custom_13',
        'custom_14'                   => 'person.custom_14',
        'custom_15'                   => 'person.custom_15',
        'custom_16'                   => 'person.custom_16',
        'sex'                         => 'person.sex',
        'dob'                         => 'person.dob',
        'login'                       => 'person.login',
        'password'                    => 'person.password',
        'email'                       => 'person.email',
        'pricing_structure_id'        => 'person.pricing_structure_id',
        'payment_terms_id'            => 'person.payment_terms_id',
        'assigned_to_person_id'       => 'person.assigned_to_person_id',
        'perm_group_id'               => 'person.perm_group_id',
        'type_id'                     => 'person.type_id',
        'type_key'                    => 'type.type_key',
        'status_type_id'              => 'person.status_type_id',
        'referral_person_id'          => 'person.referral_person_id',
        'kind'                        => 'person.kind',
        'notes'                       => 'person.notes',
        'last_ip'                     => 'person.last_ip',
        'total_balance'               => 'person.total_balance',
        'total_invoiced'              => 'person.total_invoiced',
        'token'                       => 'person.token',
        'token_time'                  => 'person.token_time',
        'owner_person_id'             => 'person.owner_person_id',
        'salutation'                  => 'person.salutation',
        'industry_type_id'            => 'person.industry_type_id',
        'rot_type_id'                 => 'person.rot_type_id',
        'commission'                  => 'person.commission',
        'total_due_today'             => 'person.total_due_today',
        'suspend_invoice'             => 'person.suspend_invoice',
        'credit_limit'                => 'person.credit_limit',
        'is_deleted'                  => 'person.is_deleted',
        'employee_tariff_rate'        => 'person.employee_tariff_rate',
        'employee_tariff_type_id'     => 'person.employee_tariff_type_id',
        'employee_minimum_stops'      => 'person.employee_minimum_stops',
        'employee_stops_rate'         => 'person.employee_stops_rate',
        'sales_person_id'             => 'person.sales_person_id',
        'person_name'                 => 'person_name(person.person_id)',
        'assigned_to_person_id_value' => 'person_name(person.assigned_to_person_id)',
        'referral_person_id_value'    => 'person_name(person.referral_person_id)',
        'owner_person_id_value'       => 'person_name(person.owner_person_id)',
        'type_id_value'               => 'type.type_value',
        'status_type_id_value'        => 'status_type.type_value',
        'industry_type_id_value'      => 'industry_type.type_value',
        'rot_type_id_value'           => 'rot_type.type_value',
        'pricing_structure_id_value'  => 'pricing_structure.structure_name',
        'house_account'               => 'person.house_account',
//        'phone_value' => 'person.phone_value',
//        'email_value' => 'person.email_value',
        'created_at'                  => 'person.date_created',
        'updated_at'                  => 'person.date_modified',
    ];

    /**
     * Repository constructor
     *
     * @param  Container  $app
     * @param  Person  $person
     * @param  string  $kind
     */
    public function __construct(
        Container $app,
        Person $person,
        $kind = 'person'
    ) {
        parent::__construct($app, $person);

        $this->selectedKind = $kind;

        $configArray = 'modconfig.'.$this->selectedKind;

        $this->searchable = $this->config->get($configArray.'.searchable');

        $this->datasetClass = $this->config->get($configArray.'.dataset', '');

        $this->columns = $this->config->get($configArray.'.columns');
    }


    /**
     * Display paginated persons list. Allows using filtering, selecting
     * columns, using type_key for type_id, status_type_key for status_type_id
     * filter and ordering
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  array  $order
     *
     * @return LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function paginate(
        $perPage = 50,
        array $columns = [
            'person.*',
            'person_name(person.person_id) AS person_name'
        ],
        array $order = []
    ) {
        $input = $this->getInput();

        // allow to use raw expressions
        $this->setRawColumns(true);

        // columns list from configuration
        $indexColumns = $this->columns['index'];

        // decide if phone, email or twilio number will be added
        $addPhone = in_array('phone_value', $indexColumns);
        $addEmail = in_array('email_value', $indexColumns);
        $addTwilioNumber = in_array('twilio_number', $indexColumns);

        if ($addPhone || $addEmail || $addTwilioNumber) {
            $cSql = new ContactSql();
            // add extra SQL for phone or/and email, twilio_number
            if ($addPhone) {
                $columns[] = $cSql->getPhoneSql('phone_value');
            }
            if ($addEmail) {
                $columns[] = $cSql->getEmailSql('email_value');
            }
            if ($addTwilioNumber) {
                $columns[] = $cSql->getTwilioNumberSql('twilio_number');
            }
        }
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();


        if (isCrmUser('bfc')) {
            $columns [] = 'person_data.data_value as employee_id';

            $model = $model->leftJoin('person_data', function ($join) {
                $join
                    ->on('person_data.person_id', '=', 'person.person_id')
                    ->where('person_data.data_key', '=', 'external_id');
            });

            if (isset($input['employee_id'])) {
                $employeeId = $input['employee_id'];

                $model = $model->where('person_data.data_value', $employeeId);
            }

            if (isset($input['custom_12'])) {
                $custom12 = $input['custom_12'];

                $model = $model->where('person.custom_12', $custom12);
            }
        }

        if(!empty($input['person_name'])) {
            $model = $model->where(DB::raw('concat(person.custom_1, " ", person.custom_3)'), 'like', '%' . trim($input['person_name'], '%') . '%');
            
            unset($input['person_name']);
            $this->setInput($input);
        }
        
        // use kind from current setting if not asked to not use it
        $useKind = $input['use_kind'] ?? 1;
        if ($useKind) {
            $this->selectedKind = $input['selected_kind'] ?? $this->selectedKind;

            $model = $model->where('person.kind', $this->selectedKind);
        }

        $searchTerm = $input['search_term'] ?? false;
        if ($searchTerm) {
            $personIds = DB::table('users')
                ->where('email', 'like', "%${searchTerm}%")
                ->pluck('person_id')
                ->all();

            if(!$personIds) {
                $personIds[] = 0;
            }
            
            $model = $model
                ->where(function ($query) use ($searchTerm) {
                    /** @var Builder|Person|Object $query */
                    $query
                        ->whereExists(function ($query) use ($searchTerm) {
                            /** @var Builder|Person|Object $query */
                            $query->select(DB::raw(1))
                                ->from('user_devices')
                                ->leftJoin('users', 'user_devices.user_id', '=', 'users.id')
                                ->whereRaw("user_devices.device_imei LIKE '%$searchTerm%'")
                                ->whereRaw('users.person_id = person.person_id');
                        })
                        ->orWhere(DB::raw("person_name(person.person_id)"), 'LIKE', "%${searchTerm}%");
                })
                ->orWhereIn('person.person_id', $personIds);
        }

        $searchTermByWords = $input['search_term_by_words'] ?? false;
        if ($searchTermByWords) {
            $words = explode(' ', $searchTermByWords);
            foreach ($words as $word) {
                if (empty($word)) {
                    continue;
                }
                $model = $model->where(function ($query) use ($word) {
                    $query->whereRaw("person_name(person.person_id) LIKE \"%$word%\"");

                    if (config('app.crm_user') === 'bfc') {
                        $query->orWhereRaw("person.person_id in  ( SELECT record_id  FROM sl_records WHERE sl_table_name='Customer' and table_name='person' AND sl_record_id LIKE  '%$word%' )");
                    }
                });
            }
        }

        // if type_key is set choose data with type_id matching this key
        if (isset($input['type_key'])) {
            $typeKey = $input['type_key'];
            // if we want to get also subtypes we set valid value to get
            // types with or without subtypes

            $withTypeKeySubtypes = $input['type_key_with_subtypes'] ?? false;

            $tmpTypeKey = explode(',', $typeKey);
            if (count($tmpTypeKey) > 0) {
                $typeId = [];
                foreach ($tmpTypeKey as $key) {
                    if ($withTypeKeySubtypes) {
                        $typeId[] = array_merge($typeId, getTypeIdByKey($key, true));
                    } else {
                        $typeId[] = getTypeIdByKey($key);
                    }
                }
            } else {
                $typeId = getTypeIdByKey($typeKey, $withTypeKeySubtypes);
            }

            if (is_array($typeId)) {
                $model = $model->whereIn('person.type_id', $typeId);
            } else {
                $model = $model->where('person.type_id', $typeId);
            }
        }

        // if status_type_key is set choose data with status_type_id matching this key
        if (isset($input['status_type_key'])) {
            $typeKey = $input['status_type_key'];

            $tmpTypeKey = explode(',', $typeKey);
            if (count($tmpTypeKey) > 0) {
                $typeId = [];
                foreach ($tmpTypeKey as $key) {
                    $typeId[] = getTypeIdByKey($key);
                }

                $model = $model->whereIn('person.status_type_id', $typeId);
            } else {
                $model = $model->where('status_type_id', getTypeIdByKey($typeKey));
            }
        }

        // verify if asked for extra user data
        $withUser = (bool)($input['with_user'] ?? false);

        // verify if want to get only persons that are/are not users
        $isUser = $input['is_user'] ?? null;
        if ($isUser !== null) {
            if ($isUser == 1) {
                $model = $model->whereNotNull('users.id');
            } elseif ($isUser == 0) {
                $model = $model->whereNull('users.id');
            }
            // in this case extra user data is needed by default
            $withUser = true;
        }

        $personType = $input['person_type'] ?? null;
        if ($personType === 'vendor') {
            $model = $model->isVendor();
        }

        $addAddresses = $input['addresses_value'] ?? null;
        if ($addAddresses) {
            $model = $model->with('addresses');
        }

        $addDefaultAddress = $input['default_address_value'] ?? null;
        if ($addDefaultAddress) {
            $model = $model->with('defaultAddress');
        }

        $addEmail = $input['email_value'] ?? null;
        if ($addEmail) {
            if (!isset($cSql)) {
                $cSql = new ContactSql();
            }
            $columns[] = $cSql->getEmailSql('email_value');
            //$model = $model->with('emailContacts');
        }

        $addPhone = $input['phone_value'] ?? null;
        if ($addPhone) {
            if (!isset($cSql)) {
                $cSql = new ContactSql();
            }
            $columns[] = $cSql->getPhoneSql('phone_value');
            //$model = $model->with('phoneContacts');
        }

        $matchAllFields = $input['all_fields'] ?? null;
        if ($matchAllFields) {
            for ($count = 4; $count < 17; $count++) {
                $model = $model
                    ->orWhere("custom_$count", 'LIKE', $matchAllFields);
            }
        }

        $sml = $input['sml'] ?? null;
        if ($sml && (config('app.crm_user') === 'bfc')) {
            $msDynamicsService = $this->app->make(MsDynamicsService::class);
            $msDynamicsService->getTechniciansTree($sml);
            $personIds = $msDynamicsService
                ->getVisibleTechnicians(0, $sml);
            $this->app->log->warning('', [$personIds]);
            $model = $model->whereIn('person.person_id', $personIds);
        }

        if (isset($input['without_empty']) && $input['without_empty'] === '1') {
            $model = $model->where('person.custom_1', '<>', DB::raw("''"));
            $model = $model->whereNotNull('person.custom_1');
        }

        // set working model
        $this->setWorkingModel($model);

        // modify working model - joins, columns etc.
        $this->setModelDetails($columns, $withUser, $input);

        if (isset($input['export']) && $input['export'] == 1) {
            $data = $model->get();
        } else {
            $data = parent::paginate($perPage, []);
        }

        // clear used model to prevent any unexpected actions
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Display paginated persons list. Allows using filtering, selecting
     * columns, using type_key for type_id, status_type_key for status_type_id
     * filter and ordering
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  array  $order
     *
     * @return LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function babayaga25(
        $perPage = 50,
        array $columns = [],
        array $order = []
    ) {
        $model = $this->model;

        $input = $this->getInput();
        if (config('app.crm_user') === 'bfc') {
            $this->availableColumns['person_name'] =
                'concat(
                    CONVERT(person_name(person.person_id) USING utf8), 
                    CONVERT(" " USING utf8), 
                    IFNULL(
                        CONVERT(
                            (SELECT sl_record_id  
                             FROM sl_records
                             WHERE sl_table_name=\'Customer\' 
                             AND table_name=\'person\' AND record_id = person.person_id)
                        USING utf8), 
                        CONVERT("" USING utf8)
                    )
                )';
        }

        if (isset($input['person_name'])) {
            $model = $model->where(
                DB::raw("concat(custom_1, ' ', custom_3)"),
                'LIKE',
                '%'.trim($input['person_name'], '%').'%'
            );

            unset($input['person_name']);

            $this->setInput($input);
        }

        if (!empty($input['type_key'])) {
            $typeId = getTypeIdByKey($input['type_key']);
            if ($typeId) {
                $model = $model->where('person.type_id', $typeId);
            }
        }

        if (!empty($input['status_type_key'])) {
            $statusTypeId = getTypeIdByKey($input['status_type_key']);
            if ($statusTypeId) {
                $model = $model->where('person.status_type_id', $statusTypeId);
            }
        }

        $model = $this->setCustomColumns($model);
        $model = $this->setCustomSort($model);
        $model = $this->setCustomFilters($model);

        if (!Arr::has($input, 'kind')) {
            $model = $model->where('person.kind', $this->selectedKind);
        }

        if (Arr::has($input, 'search_term_by_words')) {
            $searchTermByWords = $input['search_term_by_words'];
            $words = explode(' ', $searchTermByWords);
            foreach ($words as $word) {
                if (empty($word)) {
                    continue;
                }
                $model = $model
                    ->whereRaw("person_name(person.person_id) LIKE \"%$word%\"");
                if (config('app.crm_user') === 'bfc') {
                    $model = $model->orWhereRaw("person.person_id in 
                    ( SELECT record_id  
                    FROM sl_records 
                    WHERE sl_table_name='Customer' 
                    AND table_name='person' 
                    AND sl_record_id LIKE  '%$word%' )");
                }
            }
        }

        $model = $model->leftJoin('type as type', 'person.type_id', 'type.type_id');
        $model = $model->leftJoin('type as status_type', 'person.status_type_id', 'status_type.type_id');
        $model = $model->leftJoin('type as rot_type', 'person.rot_type_id', 'rot_type.type_id');
        $model = $model->leftJoin('type as industry_type', 'person.industry_type_id', 'industry_type.type_id');
        $model = $model->leftJoin(
            'pricing_structure',
            'person.pricing_structure_id',
            'pricing_structure.pricing_structure_id'
        );

        if (config('app.crm_user') === 'twc') {
            $model = $model->where(
                'person.type_id',
                getTypeIdByKey('company.customer_twc')
            )->orderBy('person.custom_1');
        }

        if ($this->request->input('without_empty') === '1') {
            $model = $model->where('person.custom_1', '<>', DB::raw("''"));
            $model = $model->whereNotNull('person.custom_1');
        }

        if (!Arr::has($input, 'sort')) {
            $model = $model->orderBy('person.custom_1');
        }

        $this->setWorkingModel($model);
        $data = parent::paginate($perPage, [], $order);
        $this->clearWorkingModel();

        if (config('app.crm_user') === 'clm') {
            $data->setCollection(collect(array_map(function ($item) {
                $item->is_house_account = (int)!empty($item->house_account);

                if ($item->is_house_account) {
                    $item->background_color = '#00ffff';
                }

                return $item;
            }, $data->items())));
        }

        return $data;
    }

    /**
     * Creates and stores new Model object
     *
     * @param  array  $input
     *
     * @return array
     *
     * @throws Exception
     * @throws mixed
     */
    public function create(array $input)
    {
        $input['kind'] = $this->selectedKind;

        $created = null;
        $groups = null;

        DB::transaction(function () use ($input, &$created, &$groups) {
            /** @var Person $model */
            $model = $this->model;

            /** @var Person $created */
            $created = $model->create($input);

            $groups = $this->setPersonGroups($created, $input['groups']);

            if (!empty($input['company_person_id'])) {
                $companyData = [
                    'person_id'        => $input['company_person_id'],
                    'member_person_id' => $created->getId(),
                    'address_id'       => 0,
                    'address_id2'      => 0,
                    'is_default'       => 0,
                    'is_default2'      => 0,
                ];

                /** @var LinkPersonCompanyRepository $linkPersonCompanyRepository */
                $linkPersonCompanyRepository = app(LinkPersonCompanyRepository::class);
                $linkPersonCompanyRepository->create($companyData);
            }
        });

        if (!empty($input['user'])) {
            try {
                $input['user']['person_id'] = $created->getId();

                /** @var UserService $userService */
                $userService = app(UserService::class);
                $userService->create($input['user']);
            } catch (Exception $e) {
                Log::error('Cannot create user account', $e->getTrace());
            }
        }

        return [$created, $groups];
    }

    /**
     * Sets groups for given Person
     *
     * @param  Person  $person
     * @param  string|array  $groups
     *
     * @return mixed
     */
    protected function setPersonGroups(Person $person, $groups)
    {
        if (!is_array($groups)) {
            $groups = [$groups];
        }

        $groups = $this->getValidGroups($groups);

        $groupsToSynchronize = [];

        foreach ($groups as $id => $value) {
            if ($value == 1) {
                $groupsToSynchronize[$id] = ['table_name' => 'person'];
            }
        }

        $person->groups()->sync($groupsToSynchronize);

        return $this->getPersonGroups($person->id);
    }

    /**
     * Get valid array groups from input (choose only existing ids)
     *
     * @param  array  $groups
     *
     * @return array
     */
    protected function getValidGroups(array $groups)
    {
        if (!$groups) {
            return $groups;
        }

        $tRepo = $this->makeRepository('Type');
        $validIds = array_keys($tRepo->getList($this->getGroupsType()));

        return array_intersect_key($groups, array_flip($validIds));
    }

    /**
     * Get group types from type table that will be used
     *
     * @return array|string
     */
    protected function getGroupsType()
    {
        return [
            'Category' => 'person_category',
            'Trade'    => 'company_trade'
        ];
    }

    /**
     * Updates Model object identified by given $id with $input data
     *
     * @param  int  $id
     * @param  array  $input
     *
     * @return array
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws mixed
     */
    public function updateWithIdAndInput($id, array $input)
    {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->where('person.kind', $this->selectedKind);
        $this->setWorkingModel($model);

        /** @var Person $object */
        $object = parent::find($id);
        $input['kind'] = $this->selectedKind;

        $groups = null;

        DB::transaction(function () use ($id, $input, &$object, &$groups) {
            $object->update($input);

            if (isset($input['groups'])) {
                $groups = $this->setPersonGroups($object, $input['groups']);
            } else {
                $groups = $this->getPersonGroups($id);
            }
        });

        $model = $this->model;
        $model = $model->where('person.kind', $this->selectedKind);
        $this->setWorkingModel($model);

        $data = [parent::find($id), $groups];
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Return Model object by given $id
     *
     * @param  int|array  $id
     * @param  array  $columns
     * @param  bool  $useKind  If set to false no kind will be used
     *
     * @return Person
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function find(
        $id,
        array $columns = [
            'person.*',
            'person_name(person.person_id) AS person_name',
        ],
        $useKind = true
    ) {
        $input = $this->getInput();

        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        $model = $model->with('addresses:address_id,person_id,address_1,address_2,city,country,state,zip_code,address_name');

        /* Use kind from current setting if not asked to not use it (but also
           $useKind needs to be set to true to allow use this function also without
           kind for example find(233, ['type_id'], false)
        */
        $useKindRequest = $this->request->input('use_kind', 1);
        if ($useKindRequest && $useKind) {
            $model = $model->where('person.kind', $this->selectedKind);
        }

        $this->setWorkingModel($model);

        $this->setModelDetails($columns, false, $input);

        /** @var Person $record */
        $record = parent::find($id);
        $this->clearWorkingModel();

        if(!empty($record->addresses)) {
            $record->address = array_filter($record->addresses->toArray(), function ($item) {
                    return isset($item['address_name']) && $item['address_name'] === 'Home Address';
                })[0] ?? $record->addresses[0] ?? null;
        } else {
            $record->address = null;
        }

        unset($record->addresses);

        /** @var UserRepository $uRepo */
        $uRepo = $this->makeRepository('User');

        // possible roles for user
        $detailed = $this->request->query('detailed', '');
        if ($detailed != '') {
            if ($record->user_id) {
                /* get all system available roles and mark which roles
                   are assigned to user assigned to person */
                $record['user_roles'] = $uRepo->getUserRoles($record->user_id);

                /** @var User $user */
                $user = $record->user;
                $companyPersonId = $record['company_person_id'] = $user->getCompanyPersonId();

                if ($companyPersonId) {
                    $clientPortalUserService = $this->app->make(ClientPortalUserService::class);
                    $settingsData = $clientPortalUserService->getSettings($companyPersonId);

                    if ($settingsData && $settingsData->settings) {
                        $settings = json_decode($settingsData->settings);

                        if ($settings && $settings->logo) {
                            $logoId = $settings->logo->id;
                            $fileService = $this->app->make(FileService::class);
                            $links = $fileService->getFileLinks([$logoId], ['logo.png']);

                            $record['client_portal_logo'] = $links['links'][$logoId];
                        }
                    }
                }
            } else {
                // get all system available roles
                $record['user_roles'] = $uRepo->getUserRoles();
            }
        }

        return $record;
    }

    /**
     * Return Model object by given $id ignoring kind
     *
     * @param  int|array  $id
     *
     * @return Person
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function findAny($id)
    {
        return $this->find($id, [
            'person.*',
            'person_name(person.person_id) AS person_name',
        ], false);
    }

    /**
     * Add joins and select columns to working model depending on 'detailed' parameter in url
     *
     * @param  array  $columns
     * @param  bool  $findUserId
     */
    protected function setModelDetails(array $columns, $findUserId = false, $input = [])
    {
        /** @var Builder|Person|Object $model */
        $model = $this->getModel();

        $detailed = $input['detailed'] ?? '';

        // add user id and user email in case it's needed
        if ($findUserId || $detailed != '') {
            $model = $model
                ->leftJoin('users', 'person.person_id', '=', 'users.person_id')
                ->where(function ($q) {
                    $q
                        ->whereNull('users.id')
                        ->orWhereRaw('users.id = (select max(id) from users u2 where u2.person_id = person.person_id)');
                });
            $columns[] = 'users.id AS `user_id`';
            $columns[] = 'users.email AS `user_email`';
            $columns[] = 'users.last_login_at AS `last_visit_date`';
        }

        // add extra data in case detailed view is expected (used for update)
        if ($detailed != '') {
            $dataset = $this->getDataset();
            $data = $dataset->getDetailedData('update');

            foreach ($data['joins'] as $join) {
                $model = $model->leftJoin($join[0], $join[1], $join[2], $join[3]);
            }
            $columns = array_merge($columns, $data['columns']);
        }

        // whether columns were used from user input
        $userInputColumns = false;

        // verify if user has chosen any columns
        $selDefColumns = $this->getColumnsList();

        if ($selDefColumns) {
            /* by default it's only  '*', but user can also use only one column
               (id) so we need to ignore when there is only one element and
               this element id `*`
            */
            if ($selDefColumns != '*' && ($selDefColumns[0] != '*' || count($selDefColumns) != 1)) {
                $userInputColumns = true;

                // add `person` table prefix for all columns (to avoid
                // confusion after join with users)
                foreach ($selDefColumns as $key => $val) {
                    if (strpos($val, '.') === false) {
                        $selDefColumns[$key] = 'person.'.$val;
                    }
                }

                // verify if we want to display person_name
                $personName = array_search(
                    'person.person_name',
                    $selDefColumns
                );
                if ($personName !== false) {
                    // set valid person_name and unset invalid
                    unset($selDefColumns[$personName]);

                    $selDefColumns[] = 'person_name(person.person_id) AS person_name';

                    // no user input ordering - use by person_name
                    if (!$this->getInputOrderArray()) {
                        $model
                            = $model->orderByRaw('person_name(person.person_id) ASC');
                    }
                }

                // add user_id always when $findUserId = true
                if ($findUserId && !in_array('user_id', $selDefColumns)) {
                    $selDefColumns[] = 'users.id AS `user_id`';
                }

                // select only columns from input
                $model = $model->selectRaw(implode(', ', $selDefColumns));

                /* remove fields from input because we used them - we don't want
                  to have double columns in SQL query */
                $input = $this->getInput();
                unset($input['fields']);
                $this->setInput($input);
            }
        }

        // use default columns in case there are no selected from input
        if ($columns && !$userInputColumns) {
            $model = $model->selectRaw(implode(', ', $columns));
        }

        // set working model to modified
        $this->setWorkingModel($model);
    }

    /**
     * Gets dataset for class
     *
     * @return mixed
     */
    public function getDataset()
    {
        $class = $this->getDatasetClassName();

        return new $class($this->app, $this->selectedKind);
    }

    /**
     * Gets dataset class name
     *
     * @return string
     */
    public function getDatasetClassName()
    {
        $className = trim($this->datasetClass);

        if ($className == '') {
            $className = ucfirst($this->selectedKind).'Dataset';
        }

        return '\\App\\Modules\\Person\\Datasets\\'.$className;
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

        if (config('app.crm_user') === 'fs' && $action === 'store') {
            $data['fields']['company_person_id'] = [
                'type'  => 'input',
                'rules' => ['required']
            ];
        }

        return $data;
    }

    /**
     * Get fields labels
     *
     * @param $action
     *
     * @return array
     */
    public function getLabels($action)
    {
        $dataset = $this->getDataset();

        return $dataset->getLabels($action);
    }

    /**
     * Wrapper for show - finds the item and wraps output together with
     * frontend validation rules, labels and datasets
     *
     * @param  int  $id
     * @param  bool  $full
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function show(
        $id,
        $full = false
    ) {
        /** @var Person $model */
        $model = $this->find($id);

        $output = [
            'item' => $model,
        ];

        $action = 'update';

        if (!$full) {
            $noFields = (int)$this->request->get('no_fields', 0);

            if ($noFields == 0) {
                $output['fields'] = $this->getLabels($action);
            }
        } else {
            $output = array_merge($output, $this->getDatasetData($action));
        }

        $withGroups = (int)$this->request->get('groups', 0);

        if ($full || ($withGroups == 1)) {
            $output['groups'] = $this->getPersonGroups($id);
        }

        return $output;
    }

    /**
     * Get Person groups together with indicating if Person with $id is assigned
     * to this group (only in case if $id !=0)
     *
     * @param  int  $id
     *
     * @return mixed
     */
    protected function getPersonGroups($id = 0)
    {
        $cRepo = $this->makeRepository('Category');

        $types = $cRepo->getTypes('person', $id, $this->getGroupsType());


        return $types;
    }

    /**
     * Create person together with addresses and contacts. Contacts not
     * assigned to any address will be assigned to default address (if exists)
     *
     * @param  array  $input
     *
     * @return array
     *
     * @throws Exception
     * @throws mixed
     */
    public function createComplex(array $input)
    {
        $addressRepo = $this->makeRepository('Address');
        $contactRepo = $this->makeRepository('Contact');

        $queues = [];
        $person = null;
        $groups = null;

        DB::transaction(function () use (
            $input,
            &$person,
            &$groups,
            &$queues,
            $addressRepo,
            $contactRepo
        ) {
            $defAddressId = 0;

            if (empty($input['dob'])) {
                $input['dob'] = null;
            }

            list($person, $groups) = $this->create($input);

            if (isset($input['addresses'])) {
                // add addresses
                foreach ($input['addresses'] as $address) {
                    $address['person_id'] = $person->id;
                    list($addressObj, $queueData)
                        = $addressRepo->create($address);
                    if ($addressObj->is_default == 1) {
                        $defAddressId = $addressObj->id;
                    }

                    $queueData['record_id'] = $addressObj->id;
                    $queues[] = $queueData;

                    if (!isset($address['contacts'])) {
                        continue;
                    }
                    // add contacts
                    foreach ($address['contacts'] as $contact) {
                        if (!empty($contact['value'])) {
                            $contact['person_id'] = $person->id;
                            $contact['address_id'] = $addressObj->id;
                            $contactRepo->create($contact);
                        }
                    }
                }
            }

            if (isset($input['contacts'])) {
                // add contacts not assigned by user to any address
                foreach ($input['contacts'] as $contact) {
                    if (!empty($contact['value'])) {
                        $contact['person_id'] = $person->id;
                        $contact['address_id'] = $defAddressId;
                        $contactRepo->create($contact);
                    }
                }
            }
        });

        return [$person, $groups, $queues];
    }

    /**
     * Get person list for work_order module
     *
     * @param  array  $typeId  Allowed person type_id properties
     * @param  array|int  $statusTypeId  Allowed person status_type_id propertied
     * @param  bool  $useKind  Whether to use kind (person or company)*
     * @param  bool  $statusTypeIdNotOperator
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function getWoList(
        array $typeId,
        $statusTypeId = [],
        $useKind = true,
        $statusTypeIdNotOperator = false
    ) {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        if ($useKind) {
            $model = $model->where('kind', $this->selectedKind);
        }

        $model = $model->whereIn('type_id', $typeId);

        if ($statusTypeId) {
            if ($statusTypeIdNotOperator) {
                $model = $model->where('status_type_id', '<>', $statusTypeId);
            } else {
                $model = $model->where('status_type_id', $statusTypeId);
            }
        }

        $model
            =
            $model->selectRaw('person_id, person_name(person_id) AS person_name')
                ->orderBy('person_name');
        $this->setWorkingModel($model);

        $data = parent::pluck('person_name', 'person_id');
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get person list (person_name and person_id)
     *
     * @param  array  $ids  Persons to include on list
     *
     * @return mixed
     */
    public function getList(array $ids = [])
    {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model
            ->isNotDeleted()
            ->selectRaw('person_id, person_name(person_id) AS person_name');

        if ($ids) {
            $model = $model->whereIn('person_id', $ids);
        }

        $this->setWorkingModel($model);

        $data = parent::pluck('person_name', 'person_id');
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get person data
     *
     * @param  int  $personId
     * @param  string|array  $columns
     * @param  bool  $useKind
     *
     * @return Builder|Person|array
     *
     * @throws InvalidArgumentException
     */
    public function getPersonData($personId, $columns = null, $useKind = false)
    {
        if (!$columns) {
            return [];
        }

        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model
            ->isNotDeleted();

        if ($useKind) {
            $model = $model->where('kind', $this->selectedKind);
        }
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        foreach ($columns as $k => $v) {
            if ($v == 'person_name') {
                $columns[$k] = 'person_name(person_id) AS person_name';
            }
        }

        return $model
            ->where('person_id', $personId)
            ->selectRaw(implode(', ', $columns))
            ->first();
    }

    /**
     * Get person name
     *
     * @param  int  $personId
     * @param  bool  $useKind
     *
     * @return string|null
     *
     * @throws InvalidArgumentException
     */
    public function getPersonName($personId, $useKind = false)
    {
        $person = $this->getPersonData($personId, 'person_name', $useKind);
        if ($person) {
            return $person->person_name;
        }

        return null;
    }

    /**
     * Get vendors that might be assigned to Work Order
     *
     * @param  int  $workOrderId
     * @param  int  $onPage
     * @param  string|null  $type
     * @param  string  $name
     * @param  int  $regionId
     * @param  int  $tradeId
     * @param  string  $jobType
     *
     * @return array|\Illuminate\Database\Eloquent\Collection|Paginator
     *
     * @throws InvalidArgumentException
     */
    public function getVendorsToAssignForWo(
        $workOrderId,
        $onPage,
        $type,
        $name,
        $regionId,
        $tradeId,
        $jobType
    ) {
        $regionId = (int)$regionId;
        $tradeId = (int)$tradeId;

        $employee = getTypeIdByKey('person.employee');
        $technician = getTypeIdByKey('person.technician');
        $supplier = getTypeIdByKey('company.supplier');
        $vendor = getTypeIdByKey('company.vendor');

        $statusActive = getTypeIdByKey('company_status.active');

        $columns = [
            'person_id',
            'person_name(person_id) as person_name',
            'status_type_id',
            "IF (status_type_id = {$statusActive}, false, true) AS mark_as_disabled",
            'custom_9',
            'kind',
            'person.status_type_id',
            '(SELECT type_value FROM type t WHERE
               t.type_id = person.status_type_id) AS status_type_id_value',
            "IFNULL(person.notes, '') as notes",
            "(select CONCAT_WS(',',address_1, city) from address where
                address.person_id=person.person_id and
					is_default=1 limit 1) as address",
        ];


        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model
            ->isNotDeleted()
            ->selectRaw(implode(', ', $columns));

        // first we select record based on type - either supplier, employee,
        // company or all of them
        if ($type == 'supplier') {
            $model = $model
                ->where('kind', 'company')
                ->where('type_id', $supplier);
        } elseif ($type == 'employee') {
            $model = $model->where('kind', 'person')->where(function ($q) use (
                $technician,
                $employee
            ) {
                /** @var Builder $q */
                $q
                    ->where('type_id', $technician)
                    ->orWhere('type_id', $employee);
            });
        } elseif ($type == 'company') {
            $model = $model
                ->where('kind', 'company')
                ->where('type_id', $vendor);
        } else {
            $model = $model->where(function ($mQ) use (
                $vendor,
                $supplier,
                $technician,
                $employee
            ) {
                /** @var Builder $mQ */
                $mQ->where(function ($q) use ($vendor, $supplier) {
                    /** @var Builder $q */
                    $q
                        ->where('kind', 'company')
                        ->where(function ($q2) use (
                            $vendor,
                            $supplier
                        ) {
                            /** @var Builder $q2 */
                            $q2
                                ->where('type_id', $vendor)
                                ->orWhere('type_id', $supplier);
                        });
                })->orWhere(function ($q) use ($technician, $employee) {
                    /** @var Builder $q */
                    $q
                        ->where('kind', 'person')
                        ->where(function ($q2) use (
                            $technician,
                            $employee
                        ) {
                            /** @var Builder $q2 */
                            $q2
                                ->where('type_id', $technician)
                                ->orWhere('type_id', $employee);
                        });
                });
            });
        }

        // if search for name, we use this filter
        if ($name != '') {
            $model = $model->where(function ($q) use ($name) {
                /** @var Builder $q */
                $q
                    ->where('custom_3', 'LIKE', '%'.$name.'%')
                    ->orWhere('custom_1', 'LIKE', '%'.$name.'%');
            });
        }

        // if search for region, we use this filter
        if ($regionId) {
            $model = $model->whereIn('person_id', function ($q) use ($regionId) {
                /** @var Builder $q */
                $q
                    ->select('table_id')->from('link_category_region')
                    ->leftJoin(
                        'category',
                        'category.category_id',
                        '=',
                        'link_category_region.category_id'
                    )
                    ->where('table_name', 'person')
                    ->where('region_id', $regionId);
            });
        }

        // if search for trade, we use this filter
        if ($tradeId) {
            $model = $model->whereIn('person_id', function ($q) use ($tradeId) {
                /** @var Builder $q */
                $q
                    ->select('table_id')
                    ->from('category')
                    ->where('table_name', 'person')
                    ->where('type_id', $tradeId);
            });
        }

        // if search for work job_type, we use this filter
        if ($jobType && $jobType == 'work') {
            $model = $model->whereNotIn(
                'person_id',
                function ($q) use ($workOrderId) {
                    /** @var Builder $q */
                    $q
                        ->select('person_id')
                        ->from('link_person_wo')
                        ->where('work_order_id', $workOrderId)
                        ->where('link_person_wo.type', 'work')
                        ->where('is_disabled', 0);
                }
            );
        }

        $this->setWorkingModel($model);
        $data = parent::paginate($onPage, []);
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get employees
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function getEmployees()
    {
        $type = $this->getRepository('Type');

        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        return $model
            ->selectRaw(implode(', ', [
                'person_id',
                'custom_1',
                'custom_3',
                'person_name(person_id) as person_name',
                'notes',
            ]))
            ->where('kind', $this->selectedKind)
            ->where(
                'status_type_id',
                '<>',
                $type->getIdByKey('company_status.disabled')
            )// TODO: is it not person_status.disabled?
            ->whereIn('type_id', $type->getIdByKey('person.employee', true))
            ->orderBy('custom_3')
            ->get();
    }

    /**
     * Get owners
     *
     * @param  string  $type
     *
     * @return Person|Company|Collection|Person[]|Company[]
     *
     */
    public function getOwners($ownerType = 'person.owner')
    {
        $type = $this->getRepository('Type');

        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        return $model
            ->selectRaw(implode(', ', [
                'person_id',
                'person_name(person_id) as person_name',
            ]))
            ->where('status_type_id', '<>', $type->getIdByKey('company_status.disabled'))
            ->whereIn('type_id', $type->getIdByKey($ownerType, true))
            ->orderBy('custom_1')
            ->get();
    }

    /**
     * Get person list with given status type based on distinct entries
     * in given table
     *
     * @param  string  $tableName
     * @param  null  $statusTypeIds
     * @param  string  $personId
     *
     * @return array
     */
    public function getDistinctFromList($tableName, $statusTypeIds = null, $personId = 'person_id')
    {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        $model = $model
            ->selectRaw(implode(', ', [
                'person_id',
                'person_name(person_id) as person_name',
            ]))
            ->whereIn('person_id', function ($q) use ($tableName, $personId) {
                /** @var Builder $q */
                $q
                    ->select($personId)
                    ->from($tableName)
                    ->distinct();
            });

        if ($statusTypeIds) {
            if (!is_array($statusTypeIds)) {
                $statusTypeIds = [$statusTypeIds];
            }

            $model = $model->whereIn('status_type_id', $statusTypeIds);
        }

        return $model
            ->orderBy('person_name')
            ->get();
    }

    /**
     * Find person by IMEI
     *
     * @param  string  $imei
     *
     * @return Builder|Person
     *
     * @throws InvalidArgumentException
     */
    public function findByImei($imei)
    {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model->isNotDeleted();

        // find person by IMEI
        $person = $model
            ->where('custom_15', '<>', '')
            ->whereRaw(
                "concat(';',custom_15,';') LIKE concat('%;', ?, ';%')",
                [$imei]
            )
            ->first();

        if (!$person) {
            // person not found - remove non-digits from IMEI and try one more time
            $imei = preg_replace('/[^\D]/i', '', $imei);
            $person = $model
                ->where('custom_15', '<>', '')
                ->whereRaw(
                    "concat(';',custom_15,';') LIKE concat('%;', ?, ';%')",
                    [$imei]
                )
                ->first();
        }

        return $person;
    }

    /**
     * Delete a specific employee
     *
     * @param  int  $id
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteEmployee($id)
    {
        /** @var Builder|Person $employee */
        $employee = new Person();

        $employee = $employee->where('person_id', '=', $id);
        $employee = $employee->isNotDeleted();

        $typeId = getTypeIdByKey('person.employee', true);

        if (is_array($typeId)) {
            $employee = $employee->whereIn('person.type_id', $typeId);
        } else {
            $employee = $employee->where('person.type_id', $typeId);
        }

        $employee = $employee->first();

        if ($employee) {
            $employee->setIsDeleted(true);
            $employee->save();

            return true;
        }

        throw with(new ModelNotFoundException())
            ->setModel(Person::class);
    }

    /**
     * Delete Client Person User
     *
     * @param  int  $id
     *
     * @return bool
     * @throws Exception
     * @throws \Throwable
     */
    public function deleteClientPortalUser($id)
    {

        /** @var User|Builder $user */
        $user = new User();
        $user = $user->where('person_id', '=', $id)->first();

        if ($user != null && $user->company_person_id != null) {
            /** @var Person|Builder $person */
            $person = new Person();
            $person = $person->find($id);

            if ($person->isNotDeleted()) {
                $person->setStatusTypeId(getTypeIdByKey("person_status.disabled"));
                $person->setIsDeleted(true);
                $person->saveOrFail();
                $user->delete();
                return true;
            }
        } else {
            return false;
        }
        return false;
    }

    /**
     * Disable a specific employee
     *
     * @param  int  $id
     *
     * @return int
     *
     * @throws Exception
     */
    public function disableEmployee($id)
    {
        /** @var Builder|Person $employee */
        $employee = new Person();

        $employee = $employee->where('person_id', '=', $id);
        $employee = $employee->isNotDeleted();

        $typeId = getTypeIdByKey('person.employee', true);

        if (is_array($typeId)) {
            $employee = $employee->whereIn('person.type_id', $typeId);
        } else {
            $employee = $employee->where('person.type_id', $typeId);
        }

        $employee = $employee->first();

        if ($employee) {
            $typeRepository = $this->app->make(TypeRepository::class);

            $disabledStatus = $typeRepository->findByKey('company_status.disabled');

            $employee->setStatusTypeId($disabledStatus->getId());
            $employee->save();

            return $disabledStatus;
        }

        throw with(new ModelNotFoundException())
            ->setModel(Person::class);
    }

    /**
     * Search person by text
     *
     * @param  string  $searchKey
     * @param  array  $columns
     *
     * @return Person[]|Collection
     */
    public function search(
        $searchKey,
        array $columns = ['person.*']
    ) {
        /** @var Builder|Object|Person $model */
        $model = $this->getModel();
        $model = $model->isNotDeleted();

        //$columns[] = 'person.type_id';
        $columns[] = 'type.type_value as type';

        //$columns[] = 'person.status_type_id';
        $columns[] = 'status_type.type_value as status';

        //$columns[] = 'person.pricing_structure_id';
        $columns[] = 'pricing_structure.structure_name as tariff';

        //$columns[] = 'person.assigned_to_person_id';
        $columns[] = 'person_name(person.assigned_to_person_id) as assigned_to';

        $keywords = explode(' ', str_replace('  ', ' ', trim($searchKey)));

        $model = $model
            ->where(function ($query) use ($keywords, $searchKey) {
                /** @var Builder|Object|Person $query */
                $query
                    ->nameContainsWords($keywords, true)
                    ->customContains($searchKey, true);
            })
            ->leftJoin('type', 'type.type_id', '=', 'person.type_id')
            ->leftJoin('type as status_type', 'status_type.type_id', '=', 'person.status_type_id')
            ->leftJoin(
                'pricing_structure',
                'pricing_structure.pricing_structure_id',
                '=',
                'person.pricing_structure_id'
            )
            ->leftJoin('type as payment_terms', 'payment_terms.type_id', '=', 'person.payment_terms_id')
            ->selectRaw(implode(', ', $columns));

        return $model->get();
    }

    /**
     * @param  array  $columns
     * @param  bool  $withData
     *
     * @return Person[]|Collection
     */
    public function getTechnicians(
        array $columns = [
            'person.*',
        ],
        $withData = true
    ) {
        /** @var Builder|Object|Person $model */
        $model = $this->getModel();

        if ($withData) {
            $model = $model->with('personData');
        }

        $model = $model
            ->isTechnician()
            ->orderBy('custom_1')
            ->orderBy('custom_3');

        $model = $model
            ->selectRaw(implode(', ', $columns));

        return $model->get();
    }

    /**
     * @return mixed
     */
    public function getTechnicianList()
    {
        $employeeTypeId = getTypeIdByKey('person.employee');
        $activeTypeId = getTypeIdByKey('company_status.active');

        return $this->model
            ->select([
                DB::raw('person_name(person_id) as person_name'),
                'person_id'
            ])
            ->where('type_id', $employeeTypeId)
            ->where('status_type_id', $activeTypeId)
            ->orderBy('person_name')
            ->pluck('person_name', 'person_id')
            ->all();
    }

    /**
     * Remote select
     *
     * @param      $searchValue
     * @param  bool  $useSLRecords
     *
     * @return array|static[]
     *
     * @throws InvalidArgumentException
     */
    public function remoteSelect(
        $searchValue,
        $useSLRecords = false
    ) {
        /** @var Builder|Person|Object $model */
        $model = $this->model;
        $model = $model
            ->isNotDeleted()
            ->where(function ($query) use ($searchValue, $useSLRecords) {
                /** @var Builder $query */
                $query
                    ->where('custom_1', 'like', "%$searchValue%");

                if ($useSLRecords) {
                    $query->orWhere('sl_records.sl_record_id', 'like', "%$searchValue%");
                }
            });

        if ($useSLRecords) {
            $model = $model
                ->leftJoin('sl_records', function ($join) {
                    /** @var JoinClause $join */
                    $join
                        ->on('sl_records.record_id', '=', 'person.person_id')
                        ->where('sl_records.sl_table_name', '=', 'Customer')
                        ->where('sl_records.table_name', '=', 'person');
                });
        }

        $prefix = $useSLRecords ? "IFNULL(CONCAT(sl_records.sl_record_id, ' - '),'')," : '';

        return $model
            ->limit(20)
            ->distinct()
            ->selectRaw(implode(',', [
                'person.person_id as person_id',
                "CONCAT(${prefix}person.custom_1) as custom_1",
            ]))
            ->get();
    }

    public function getRsmSummary($personId)
    {
        $creditCardRepository = $this->app->make(CreditCardTransactionRepository::class);
        $notLinkedTransactions = $creditCardRepository->getNotLinkedTransactions($personId);
        $unapprovedTransactions = $creditCardRepository->getUnpprovedTransactions($personId);

        $unapprovedReimbursement = $this->app->make(ReceiptRepository::class)->getUnapprovedReimbursementReceipts($personId);
        $unresolvedSiteIssues = $this->app->make(AddressIssueRepository::class)->getUnresolvedSiteIssues($personId);
        $unapprovedNightOutTown = $this->app->make(LastCheckOutRepository::class)->getUnapprovedNightOutTown($personId);
        $unlockedTimeSheets = $this->app->make(TimeSheetRepository::class)->getNotLockedTimesheet($personId);

        //photos types
        $type_ids['interior_cab'] = getTypeIdByKey('weekly_inspections.interior_cab');
        $type_ids['exterior_truck'] = getTypeIdByKey('weekly_inspections.exterior_truck_van');
        $type_ids['interior_bed'] = getTypeIdByKey('weekly_inspections.interior_bed');
        $type_ids['interior_truck'] = getTypeIdByKey('weekly_inspections.interior_truck_van');

        $storage_photo = getTypeIdByKey('weekly_storage_unit.photo');

        $this->request->request->add(['file_types_ids' => implode(',', $type_ids)]);
        $vehiclePhotos = $this->app->make(FileRepository::class)->getInspectionPhotos();
        $this->request->request->remove('file_types_ids');

        $this->request->request->add(['file_types_ids' => $storage_photo]);
        $storagePhotos = $this->app->make(FileRepository::class)->getInspectionPhotos();
        $this->request->request->remove('creator_person_id');

        return [
            'notLinkedTransactions'           => $notLinkedTransactions,
            'unapprovedTransactions'          => $unapprovedTransactions,
            'unapprovedReimbursementReceipts' => $unapprovedReimbursement,
            'unresolvedSiteIssues'            => $unresolvedSiteIssues,
            'unapprovedNightOutTown'          => $unapprovedNightOutTown,
            'unlockedTimeSheets'              => $unlockedTimeSheets,
            'vehiclePhotos'                   => $vehiclePhotos,
            'storagePhotos'                   => $storagePhotos,
        ];
    }

    public function getCustomerContacts(int $companyPersonId, array $contactTypeIds = [])
    {
        return $this->model
            ->select([
                'contact.contact_id',
                'person.person_id',
                DB::raw('contact.value AS contact_value'),
                DB::raw('contact.contact_id, contact.is_default'),
                DB::raw('type.type_key AS contact_key'),
                DB::raw('type.type_value AS contact_type'),
                DB::raw('person_name(person.person_id) AS customer_name'),
                DB::raw('IF(person.custom_9 = type.type_value, 1, 0) AS is_prefered')
            ])
            ->join('contact', 'contact.person_id', '=', 'person.person_id')
            ->join('type', 'type.type_id', '=', 'contact.type_id')
            ->where(function ($query) use ($companyPersonId, $contactTypeIds) {
                if ($contactTypeIds) {
                    if (!is_array($contactTypeIds)) {
                        $contactTypeIds = [$contactTypeIds];
                    }

                    $query->whereIn('contact.type_id', $contactTypeIds);
                }

                $query
                    ->where('person.person_id', $companyPersonId)
                    ->orWhereRaw(
                        'person.person_id in (select member_person_id from link_person_company where person_id = ?)',
                        [$companyPersonId]
                    );
            })
            ->orWhereRaw(
                'person.person_id in (select person_id from link_person_company where member_person_id = ?)',
                [$companyPersonId]
            )
            ->groupBy('contact.contact_id')
            ->orderBy('person.custom_3')
            ->orderBy('person.custom_1')
            ->orderBy('contact.type_id')
            ->orderBy('contact.is_default')
            ->get();
    }


    public function export(array $filters)
    {
        $filters['export'] = 1;

        $this->setInput($filters);

        $columnsMap = [
            'person_id'                   => 'Person ID',
            'custom_1'                    => 'First Name',
            'custom_3'                    => 'Last Name',
            'custom_2'                    => 'Middle Name',
            'custom_4'                    => 'Custom 4',
            'salutation'                  => 'Salutation',
            'sex'                         => 'Sex',
            'dob'                         => 'Date of birth',
            'assigned_to_person_id_value' => 'Assigned to',
            'type_id_value'               => 'Type',
            'status_type_id_value'        => 'Status',
            'industry_type_id_value'      => 'Industry',
            'rot_type_id_value'           => 'ROT',
            'referral_person_id_value'    => 'Referral',
            'owner_person_id_value'       => 'Owner',
            'notes'                       => 'Notes',
            'pricing_structure_id_value'  => 'Pricing structure',
            'custom_10'                   => 'Parks at BFC facility',
            'custom_12'                   => 'Integration with wGLN (Route Planner)',
            'phone_value'                 => 'Phone',
            'email_value'                 => 'Email'
        ];

        if (isCrmUser('bfc')) {
            $columnsMap = array_merge(['employee_id' => 'SL #'], $columnsMap);
        }

        $personData = [];
        $data = $this->paginate()->toArray();
        foreach ($data as $index => $person) {
            $personData[$index] = [];

            foreach ($columnsMap as $column => $label) {
                if (isset($person[$column])) {
                    $personData[$index][$label] = $person[$column];
                } else {
                    $personData[$index][$label] = null;
                }
            }
        }

        return $personData;
    }
}
