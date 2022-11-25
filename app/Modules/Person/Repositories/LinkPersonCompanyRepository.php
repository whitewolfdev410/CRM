<?php

namespace App\Modules\Person\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Person\Models\LinkPersonCompany;
use App\Modules\Person\Models\Person;
use App\Modules\User\Services\ClientPortalUserService;
use Illuminate\Container\Container;
use App\Modules\Person\Http\Requests\LinkPersonCompanyRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Person repository class
 */
class LinkPersonCompanyRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [
            'person_id',
            'member_person_id',
            'address_id',
            'address_id2',
            'position',
            'position2',
            'start_date',
            'end_date',
            'type_id',
            'type_id2',
            'is_default',
            'is_default2',
        ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param LinkPersonCompany $personCompany
     */
    public function __construct(
        Container $app,
        LinkPersonCompany $personCompany
    ) {
        parent::__construct($app, $personCompany);
    }

    /**

     * @param  int  $id
     * @param  bool  $full
     *
     * @return array
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($id, $full = false)
    {
        $output['item'] = $this->find($id, [
            '*', 
            DB::raw('person_name(member_person_id) as member_person_id_value'),
            DB::raw('t(type_id) as relationship')
        ]);

        $statusTypeIdValue = Person::select(DB::raw('t(status_type_id) as status_type_id_value'))
            ->where('person_id', $output['item']['member_person_id'])
            ->first()
            ->status_type_id_value;
        
        $output['item']['person_name'] = $output['item']['member_person_id_value'];
        $output['item']['rel_2_relationship'] = $output['item']['relationship'];
        $output['item']['rel_2_status_type_value'] = $statusTypeIdValue;
        
        if ($full) {
            $output['fields'] = $this->getRequestRules();
        }

        return $output;
    }    
    
    /**
     * {@inheritdoc}
     */
    public function create(array $input)
    {
        list($input['is_default'], $changed,
            $input['is_default2'], $changed2
            )
            = $this->changeIsDefault(
                $input['person_id'],
                $input['address_id'],
                $input['is_default'],
                $input['member_person_id'],
                $input['address_id2'],
                $input['is_default2']
            );

        $created = $this->model->create($input);

        $defChanged = [];

        if ($created) {
            $defChanged = $this->changeOtherDefaults(
                $input['person_id'],
                $input['address_id'],
                $input['is_default'],
                $input['member_person_id'],
                $input['address_id2'],
                $input['is_default2'],
                $created->link_person_company_id,
                $changed,
                $changed2
            );
        }

        return [$created, $defChanged];
    }

    /**
     * {@inheritdoc}
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $object = $this->find($id);

        list($input['is_default'], $changed, $input['is_default2'], $changed2)
            = $this->changeIsDefault(
                $input['person_id'],
                $input['address_id'],
                $input['is_default'],
                $input['member_person_id'],
                $input['address_id2'],
                $input['is_default2'],
                $object->getId()
            );

        $status = $object->update($input);

        $defChanged = [];

        if ($status) {
            $defChanged = $this->changeOtherDefaults(
                $input['person_id'],
                $input['address_id'],
                $input['is_default'],
                $input['member_person_id'],
                $input['address_id2'],
                $input['is_default2'],
                $object->getId(),
                $changed,
                $changed2
            );
        }

        return [$this->find($id), $defChanged];
    }

    /**
     * Return correct is_default and is_default2 value for Model
     *
     * @param int $personId
     * @param int $addressId
     * @param int $isDefault
     * @param int $personId2
     * @param int $addressId2
     * @param int $isDefault2
     * @param int|null $exclude
     *
     * @return array
     */
    private function changeIsDefault(
        $personId,
        $addressId,
        $isDefault,
        $personId2,
        $addressId2,
        $isDefault2,
        $exclude = null
    ) {
        $changed = false;
        $changed2 = false;

        if ($isDefault == 0) {
            $defaults = $this->getOtherDefaultsCount(
                $personId,
                $addressId,
                $exclude
            );
            if ($defaults == 0) {
                $isDefault = 1;
                $changed = true;
            }
        }

        if ($isDefault2 == 0) {
            $defaults = $this->getOtherDefaultsCount(
                $personId2,
                $addressId2,
                $exclude
            );
            if ($defaults == 0) {
                $isDefault2 = 1;
                $changed2 = true;
            }
        }

        return [$isDefault, $changed, $isDefault2, $changed2];
    }

    /**
     * Calculates number of records for $personId and $addressId that have default=1
     *
     * @param int $personId
     * @param int $addressId
     * @param int $exclude
     *
     * @return int
     */
    protected function getOtherDefaultsCount(
        $personId,
        $addressId,
        $exclude = null
    ) {
        $defaults = $this->model
            ->where(function ($q) use (
                $personId,
                $addressId,
                $exclude
            ) {
                $q->where('person_id', $personId)
                    ->where('address_id', $addressId)
                    ->where('is_default', 1);

                if ($exclude != null) {
                    $q->where('link_person_company_id', '<>', $exclude);
                }
            })->orWhere(function ($q) use (
                $personId,
                $addressId,
                $exclude
            ) {
                $q->where('member_person_id', $personId)
                    ->where('address_id2', $addressId)
                    ->where('is_default2', 1);

                if ($exclude != null) {
                    $q->where('link_person_company_id', '<>', $exclude);
                }
            });

        return $defaults->count();
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new LinkPersonCompanyRequest();

        return $req->getFrontendRules();
    }

    /**
     * Change other records default to 0 if new one is set to 1 for both
     * sets
     *
     * @param int $personId
     * @param int $addressId
     * @param int $isDefault
     * @param int $personId2
     * @param int $addressId2
     * @param int $isDefault2
     * @param int $exclude
     * @param int $changed
     * @param int $changed2
     *
     * @return array
     */
    private function changeOtherDefaults(
        $personId,
        $addressId,
        $isDefault,
        $personId2,
        $addressId2,
        $isDefault2,
        $exclude,
        $changed,
        $changed2
    ) {
        $notDef = [];

        if ($isDefault == 1 && $changed === false) {
            $notDef = $this->changeOtherDefaultsSingle(
                $personId,
                $addressId,
                $exclude
            );
        }

        if ($isDefault2 == 1 && $changed2 === false) {
            $notDef = array_merge(
                $notDef,
                $this->changeOtherDefaultsSingle(
                    $personId2,
                    $addressId2,
                    $exclude
                )
            );
        }

        return $notDef;
    }

    /**
     * Change other records default to 0 if new one is set to 1 for single set
     *
     * @param int $personId
     * @param int $addressId
     * @param int $exclude
     *
     * @return array
     */
    protected function changeOtherDefaultsSingle(
        $personId,
        $addressId,
        $exclude
    ) {
        $notDef = [];

        $defaults = $this->model
            ->where(function ($q) use (
                $personId,
                $addressId,
                $exclude
            ) {
                $q->where('person_id', $personId)
                    ->where('address_id', $addressId)
                    ->where('is_default', 1)
                    ->where('link_person_company_id', '<>', $exclude);
            })->orWhere(function ($q) use (
                $personId,
                $addressId,
                $exclude
            ) {
                $q->where('member_person_id', $personId)
                    ->where('address_id2', $addressId)
                    ->where('is_default2', 1)
                    ->where('link_person_company_id', '<>', $exclude);
            })
            ->select(
                'link_person_company_id',
                'address_id',
                'address_id2',
                'is_default',
                'is_default2'
            )->get();

        foreach ($defaults as $def) {
            $record = [];
            $record['id'] = $def->link_person_company_id;

            if ($def->address_id == $addressId) {
                $def->is_default = 0;
                $record['is_default'] = 0;
            }
            if ($def->address_id2 == $addressId) {
                $def->is_default2 = 0;
                $record['is_default2'] = 0;
            }
            $def->save();


            $notDef[] = $record;
        }

        return $notDef;
    }

    /**
     * Assign $personId relationships person-company that are not assigned
     * to any address to $addressId
     *
     * @param int $personId
     * @param int $addressId
     */
    public function assignNotAssigned($personId, $addressId)
    {
        $empty = $this->model->where(function ($q) use ($personId) {
            $q->where('person_id', $personId)
                ->where('address_id', 0);
        })
            ->orWhere(function ($q) use ($personId) {
                $q->where('member_person_id', $personId)
                    ->where('address_id2', 0);
            })
            ->select(
                'link_person_company_id',
                'person_id',
                'member_person_id',
                'address_id',
                'address_id2'
            )->get();

        foreach ($empty as $emp) {
            if ($emp->person_id == $personId) {
                $emp->address_id = $addressId;
            }
            if ($emp->member_person_id == $personId) {
                $emp->address_id2 = $addressId;
            }

            $emp->save();
        }
    }

    /**
     * Get addresses (or address) for given person
     *
     * @param int $personId
     * @param bool $first
     *
     * @return Collection|LinkPersonCompany
     */
    public function getForPerson($personId, $first = false)
    {
        return $this->findForPerson($personId, $first);
    }

    /**
     * Link or unlink client portal user
     *
     * @param $companyId
     * @param $clientId
     * @param $shouldLink
     * @return array
     * @throws \Exception
     */
    public function linkUnlinkClientPortalUser($companyId, $clientId, $shouldLink)
    {


        /** @var LinkPersonCompany|Builder $model */
        $model = new LinkPersonCompany();

        $model = $model->whereIn('person_id', [$companyId])
            ->whereIn('member_person_id', [$clientId])->first();

        if ($shouldLink == 1) {
            if ($model == null) {
                /** @var LinkPersonCompany|Builder $model */
                $model = new LinkPersonCompany();
                $model->person_id = $companyId;
                $model->member_person_id = $clientId;

                /** @var Person|Builder $company */
                $company = new Person();
                $company = $company->find($companyId);

                $this->app->make(ClientPortalUserService::class)->setClientPortalCompanyPersonId($clientId);


                //check if company is company
                if ($company->getKind() == 'company') {
                    $model->save();
                    return [
                            'success' => true,
                            'code' => 200,
                            'message' => 'Link created'
                        ];
                } else {
                    return [
                            'success' => false,
                            'code' => 422,
                            'data' => [
                                'error' => [
                                    'message' => 'Bad person type, should be company.'
                                ]
                            ]
                        ];
                }
            } else {
                return [
                        'success' => false,
                        'code' => 422,
                        'data' => [
                            'error' => [
                                'message' => 'Link already exist.'
                            ]
                        ]
                    ];
            }
        } else {
            if ($model != null) {
                $model->delete();
                return [
                        'success' => true,
                        'code' => 200,
                        'message' => 'Link deleted'
                    ];
            } else {
                return [
                        'success' => false,
                        'code' => 422,
                        'data' => [
                            'error' => [
                                'message' => 'Not found link.'
                            ]
                        ]
                    ];
            }
        }
    }

    public function getLinkedList($person_id)
    {
        /** @var LinkPersonCompany|Builder $model */
        $model = new LinkPersonCompany();
        $model = $model->where('person_id', '=', $person_id)
            ->orWhere('member_person_id', '=', $person_id)
            ->get();

        return $model;
    }


    public function getCompany($personId)
    {
        /** @var LinkPersonCompany|Builder $model */
        $model = new Person();

        return $model
            ->select(['person.*'])
            ->join('link_person_company', 'link_person_company.person_id', '=', 'person.person_id')
            ->where('member_person_id', $personId)
            ->first();
    }
}
