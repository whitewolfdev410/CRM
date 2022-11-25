<?php

namespace App\Modules\Person\Repositories;

use App\Modules\MsDynamics\Services\MsDynamicsService;
use Illuminate\Support\Facades\App;
use App\Core\AbstractRepository;
use App\Modules\Person\Models\PersonData;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PersonData repository class
 */
class PersonDataRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container  $app
     * @param PersonData $personData
     */
    public function __construct(
        Container $app,
        PersonData $personData
    ) {
        parent::__construct($app, $personData);
    }

    /**
     * Return Person Data by given $id
     *
     * @param int   $id
     * @param int   $personId
     * @param array $columns
     *
     * @return PersonData
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdAndPerson($id, $personId, array $columns = ['*'])
    {
        /** @var PersonData|Builder $model */
        $model = $this->getModel();

        return $model
            ->ofPerson($personId)
            ->where('person_data_id', '=', $id)
            ->firstOrFail($columns);
    }

    /**
     * @param int   $personId
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateByPerson(
        $personId,
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        /** @var PersonData|Object $model */
        $model = $this->getModel();
        $model = $model->ofPerson($personId);

        $this->setWorkingModel($model);

        $pagination = parent::paginate($perPage, $columns, $order);

        $this->clearWorkingModel();

        return $pagination;
    }

    /**
     * Updates Model object identified by given $id with $input data
     *
     * @param int   $id
     * @param int   $personId
     * @param array $input
     *
     * @return PersonData
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateWithIdsAndInput($id, $personId, array $input)
    {
        /** @var PersonData|Builder $model */
        $model = $this->getModel();

        /** @var PersonData|Object $personData */
        $personData = $model
            ->ofPerson($personId)
            ->where('person_data_id', '=', $id)
            ->firstOrFail();

        return $this->performUpdate($personData, $input, true);
    }

    /**
     * Get person id by $employeeId
     * @param $employeeId
     *
     * @return int|null
     */
    public function getPersonIdByEmployeeId($employeeId)
    {
        if ($employeeId) {
            $personData = $this->model
                ->select(['person_id'])
                ->where('data_key', DB::raw("'external_id'"))
                ->where('data_value', $employeeId)
                ->first();

            if ($personData) {
                return $personData->person_id;
            }
        }
        
        return null;
    }

    /**
     * Get employee id by $personId
     * @param $personId
     *
     * @return int|null
     */
    public function getEmployeeIdByPersonId($personId)
    {
        $personData = $this->model
            ->select(['data_value'])
            ->where('data_key', DB::raw("'external_id'"))
            ->where('person_id', $personId)
            ->first();

        if ($personData) {
            return $personData->data_value;
        }

        return null;
    }

    /**
     * Get employee id by $personId
     * @param $personId
     *
     * @return int|null
     */
    public function getEmployeeIdByPersonIds($personIds)
    {
        return $this->model
            ->where('data_key', DB::raw("'external_id'"))
            ->whereIn('person_id', $personIds)
            ->pluck('data_value', 'person_id');
    }

    /**
     * Get person ids by employee ids
     * @param $employeeIds
     *
     * @return int|null
     */
    public function getPersonIdsByEmployeeIds($employeeIds)
    {
        return $this->model
            ->where('data_key', DB::raw("'external_id'"))
            ->whereIn('data_value', $employeeIds)
            ->pluck('person_id', 'data_value');
    }
    
    public function getPersonIdsByTeam($team)
    {
//        /** @var MsDynamicsService $msDynamicsService */
//        $msDynamicsService = app(MsDynamicsService::class);
//        
//        $employeeIds = $msDynamicsService->getCustomerIdsByTeam($team);
//        
//        return $this->getPersonIdsByEmployeeIds($employeeIds);

        return $this->model
            ->where('data_key', DB::raw("'team'"))
            ->where('data_value', $team)
            ->pluck('person_id')
            ->all();        
    }

    public function getPersonIdsByRegion($region)
    {
//        /** @var MsDynamicsService $msDynamicsService */
//        $msDynamicsService = app(MsDynamicsService::class);
//        
//        $employeeIds = $msDynamicsService->getCustomerIdsByRegion($region);
//
//        return $this->getPersonIdsByEmployeeIds($employeeIds);

        return $this->model
            ->where('data_key', DB::raw("'region'"))
            ->where('data_value', $region)
            ->pluck('person_id')
            ->all();
        
    }
}
