<?php

namespace App\Modules\Person\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\Person\Http\Requests\PersonDataRequest;
use App\Modules\Person\Models\PersonData;
use App\Modules\Person\Repositories\PersonDataRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

/**
 * Class PersonDataController
 *
 * @package App\Modules\Person\Http\Controllers
 */
class PersonDataController extends Controller
{
    /**
     * Person data repository
     *
     * @var PersonDataRepository
     */
    private $personDataRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param PersonDataRepository $personDataRepository
     */
    public function __construct(PersonDataRepository $personDataRepository)
    {
        $this->middleware('auth');
        $this->personDataRepository = $personDataRepository;
    }

    /**
     * Return list of Person Data
     *
     * @param Config $config
     * @param int    $personId
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function index(Config $config, $personId)
    {
        $this->checkPermissions(['person.show']);

        $onPage = $config->get('system_settings.person_data_pagination');

        $personId = (int)$personId;

        $list = $this->personDataRepository->paginateByPerson($personId, $onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Person Data
     *
     * @param int $personId
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($personId, $id)
    {
        $this->checkPermissions(['person.show']);

        $id = (int)$id;
        $data = $this->personDataRepository->show($id);

        $personId = (int)$personId;
        /** @var PersonData $personData */
        $personData = $data['item'];
        if ($personData->getPersonId() !== $personId) {
            throw (new ModelNotFoundException())->setModel($personId);
        }

        return response()->json($data);
    }

    /**
     * Store a newly created Person Data in storage.
     *
     * @param PersonDataRequest $request
     * @param int               $personId
     *
     * @return JsonResponse
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function store(PersonDataRequest $request, $personId)
    {
        $this->checkPermissions(['person.store']);

        $personId = (int)$personId;
        $modelPersonId = (int)$request->get('person_id');
        if ($modelPersonId !== $personId) {
            throw (new ModelNotFoundException())->setModel($modelPersonId);
        }

        $model = $this->personDataRepository->create($request->all());

        return response()->json([
            'item' => $model,
        ], 201);
    }

    /**
     * Update the specified Person Data in storage.
     *
     * @param PersonDataRequest $request
     * @param int               $personId
     * @param int               $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(PersonDataRequest $request, $personId, $id)
    {
        $this->checkPermissions(['person.update']);

        $id = (int)$id;
        $personId = (int)$personId;
        $model = $this->personDataRepository->updateWithIdsAndInput($id, $personId, $request->all());

        return response()->json([
            'item' => $model,
        ]);
    }

    /**
     * Remove the specified Person Data from storage.
     *
     * @param int $personId
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function destroy($personId, $id)
    {
        $this->checkPermissions(['person.destroy']);

        $id = (int)$id;
        $personId = (int)$personId;

        $this->personDataRepository->findByIdAndPerson($id, $personId);

        $this->personDataRepository->destroy($id);

        return response()->json([], 201);
    }
}
