<?php

namespace App\Modules\Person\Http\Controllers;

use App\Core\Exceptions\NoPermissionException;
use App\Core\InputFormatter;
use App\Http\Controllers\Controller;
use App\Modules\Address\Http\Requests\AddressRequest;
use App\Modules\ClientPortal\Jobs\QueuedJobManager;
use App\Modules\Contact\Http\Requests\ContactRequest;
use App\Modules\Person\Http\Requests\PersonStoreComplexRequest;
use App\Modules\Person\Http\Requests\PersonStoreRequest;
use App\Modules\Person\Http\Requests\PersonUpdateRequest;
use App\Modules\Person\Jobs\PersonExport;
use App\Modules\Person\Models\EmployeeSupervisor;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Models\PersonMenu;
use App\Modules\Person\Repositories\EmployeeRepository;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\Person\Services\PersonComplexValidatorService;
use App\Modules\Person\Services\PersonConfigService;
use App\Modules\Person\Services\PersonLedgerService;
use App\Modules\Queue\Repositories\QueuedJobRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class PersonController
 *
 * @package App\Modules\Person\Http\Controllers
 */
class PersonController extends Controller
{
    /**
     * Person repository
     *
     * @var PersonRepository
     */
    private $personRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param PersonRepository $personRepository
     */
    public function __construct(PersonRepository $personRepository)
    {
        $this->middleware('auth');
        $this->personRepository = $personRepository;
    }

    /**
     * Return list of Person
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \InvalidArgumentException
     */
    public function index(Config $config, Request $request)
    {
        $this->checkPermissions(['person.index']);

        $onPage = $config->get('system_settings.person_pagination');

        $list = $this->personRepository->paginate($onPage);

        $addFields = $request->input('with_fields', 1);
        if ($addFields) {
            /** @var Object $list */
            $list = $list->toArray();
            $list['fields'] = $this->personRepository->getLabels('index');
            $list['employeeTypes'] = getTypeIdByKey('person.employee', true);
        }

        return response()->json($list);
    }

    /**
     * Return list of Person
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \InvalidArgumentException
     */
    public function babayaga25(Config $config, Request $request)
    {
        $this->checkPermissions(['person.index']);

        $onPage = $config->get('system_settings.person_pagination');

        $list = $this->personRepository->babayaga25($onPage);

        $addFields = $request->input('with_fields', 1);
        if ($addFields) {
            /** @var Object $list */
            $list = $list->toArray();
            $list['fields'] = $this->personRepository->getLabels('index');
            $list['employeeTypes'] = getTypeIdByKey('person.employee', true);
        }

        return response()->json($list);
    }

    /**
     * Display the specified Person
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function show($id)
    {
        $this->checkPermissions(['person.show']);
        $id = (int)$id;
        $data = $this->personRepository->show($id);

        return response()->json($data);
    }

    /**
     * Return Person module configuration for store action
     *
     * @return JsonResponse
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['person.store']);
        $data = $this->personRepository->getDatasetData('store');

        return response()->json($data);
    }

    /**
     * Return Person module configuration for store action of an Employee
     *
     * @param EmployeeRepository $repository
     *
     * @return JsonResponse
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function createEmployee(EmployeeRepository $repository)
    {
        $this->checkPermissions(['person.store']);
        $data = $repository->getDatasetData('store');

        return response()->json($data);
    }

    /**
     * Return Employee list
     *
     * @param EmployeeRepository $employeeRepository
     *
     * @return JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function getEmployees(EmployeeRepository $employeeRepository)
    {
        try {
            $this->checkPermissions(['person.employees-list']);
        } catch (NoPermissionException $e) {
            $this->checkPermissions(['person-employees.list']);
        }
        
        $list = $employeeRepository->getEmployees();

        return response()->json($list);
    }
    
    /**
     * Store a newly created Person in storage.
     *
     * @param PersonStoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function store(PersonStoreRequest $request)
    {
        $this->checkPermissions(['person.store']);
        list($model, $groups) = $this->personRepository->create($request->all());

        $data['item'] = $model;
        $data['groups'] = $groups;

        return response()->json($data, 201);
    }

    /**
     * Store a newly created Person together with addresses and contacts
     * in storage.
     *
     * @param PersonStoreComplexRequest $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function storeComplex(PersonStoreComplexRequest $request)
    {
        $this->checkPermissions(['person.store.complex']);

        $pcValidator = new PersonComplexValidatorService($request, new AddressRequest(), new ContactRequest(), new InputFormatter());

        $pcValidator->validate();

        list($model, $groups, $queues) = $this->personRepository->createComplex($pcValidator->getFormattedData());

        $data['item'] = $model;
        $data['groups'] = $groups;
        $data['address_queues'] = $queues;

        return response()->json($data, 201);
    }

    /**
     * Return Person module configuration for update action
     *
     * @param int    $id
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function edit($id, Config $config)
    {
        $this->checkPermissions(['person.update']);
        $id = (int)$id;

        $data = $this->personRepository->show($id, true);
        $menu = $this->getMenu($config);
        if ($menu !== false) {
            $data['menu'] = $menu;
        }

        return response()->json($data);
    }

    /**
     * Update the specified Person in storage.
     *
     * @param PersonUpdateRequest $request
     * @param int                 $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \InvalidArgumentException
     */
    public function update(Request $request, $id)
    {
        $this->checkPermissions(['person.update']);
        $id = (int)$id;
        list($model, $groups) = $this->personRepository
            ->updateWithIdAndInput($id, $request->all());

        $data['item'] = $model;
        $data['groups'] = $groups;

        return response()->json($data);
    }

    /**
     * Remove the specified Person from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['person.destroy']);

        abort(404);
        exit;

        /*$id = (int)$id;
        $this->personRepository->destroy($id);*/
    }

    /**
     * Mark the specified Employee as deleted.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function deleteEmployee($id)
    {
        $this->checkPermissions(['person.destroy']);

        return response()->json([
            'result' => $this->personRepository->deleteEmployee((int)$id),
        ]);
    }

    /**
     * Mark the specified Employee as deleted.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function deleteClientPortalUser($id)
    {
        $this->checkPermissions(['person.destroy']);

        return response()->json([
            'result' => $this->personRepository->deleteClientPortalUser((int)$id),
        ]);
    }

    /**
     * Disable the specified Employee from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \Exception
     */
    public function disableEmployee($id)
    {
        $this->checkPermissions(['person.update']);

        return response()->json([
            'result' => $this->personRepository->disableEmployee((int)$id),
        ]);
    }

    /**
     * Gets menu items based on user permissions
     *
     * @param Config $config
     *
     * @return array|bool
     */
    protected function getMenu(Config $config)
    {
        $menu = $config->get('modconfig.person.menu');
        $menuItems = $this->getPermissionsStatus($menu);

        $menuBuilder = new PersonMenu($menuItems, $menu);

        return $menuBuilder->get();
    }

    /**
     * Get mobile configuration for current person
     *
     * @param PersonConfigService $service
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function mobileConfig(PersonConfigService $service)
    {
        $this->checkPermissions(['person.config-mobile']);

        $data = $service->getMobileConfig();

        return response()->json(['item' => $data]);
    }

    /**
     * @param PersonLedgerService $personLedgerService
     * @param int                 $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function getLedger(PersonLedgerService $personLedgerService, $id)
    {
        $this->checkPermissions(['invoice.index']);
        $this->checkPermissions(['payment.index']);
        $this->checkPermissions(['person.index']);

        $id = (int)$id;
        $entries = $personLedgerService->getLedger($id);

        return response()->json($entries);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function getSearch(Request $request)
    {
        $this->checkPermissions(['person.index']);

        $searchKey = $request->get('search_key');

        $columns = [
            'person.person_id',
            'person_name(person.person_id) as person_name',
            'person.kind',
        ];
        for ($count = 1; $count < 17; $count++) {
            $columns[] = "person.custom_$count";
        }

        $persons = $this->personRepository
            ->search($searchKey, $columns);

        return response()->json($persons);
    }
    
    public function getRsmSummary(Config $config, Request $request)
    {
        $summary = [];

        $persons = explode(",", $request->input('person_id'));

        foreach ($persons as $key => $person) {
            if (Person::find($person) != null) {
                $request->request->add(['creator_person_id' => $person]);
                $summary[$person] = $this->personRepository->getRsmSummary($person);
                $summary[$person]['name'] = $this->personRepository->getPersonName($person);
            }
        }
        
        return response()->json($summary);
    }
    
    public function getStructure(Request $request)
    {
        $structure = app(EmployeeSupervisor::class);
        if (isset($request['flattened']) && $request['flattened'] == 1) {
            if (isset($request['supervisor_id'])) {
                $employees['items'] =  $structure->getStructure('select', $request['supervisor_id']);
            } else {
                $employees['items'] =  $structure->getStructure('select');
            }
        } else {
            if (isset($request['supervisor_id'])) {
                $employees['items'] =  $structure->getStructure('array', $request['supervisor_id']);
            } else {
                $employees['items'] =  $structure->getStructure('array');
            }
        }
        
        return response()->json($employees);
    }

    /**
     * @param  Request  $request
     * @param  QueuedJobRepository  $queuedJobRepository
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function export(Request $request, QueuedJobRepository $queuedJobRepository)
    {
        $this->checkPermissions(['person.export']);

        $filters = $request->all();

        $jobId = $queuedJobRepository->getExistingReportByFilters($filters, 'person');
        if ($jobId) {
            return response()->json([
                'message' => 'Export has been queued',
                'job_id'  => $jobId
            ]);
        } else {
            /** @var QueuedJobManager $queuedJobManager */
            $queuedJobManager = app(QueuedJobManager::class);
            $job = new PersonExport($filters);

            $tracking = $queuedJobManager->queue($job->onQueue('export'));
            $queuedJobManager->setData($tracking['tracking_id'], $request->all());
            $queuedJobManager->setPersonId($tracking['tracking_id'], Auth::user()->getPersonId());

            return response()->json([
                'message' => 'Export has been queued',
                'job_id'  => $tracking['tracking_id']
            ]);
        }
    }
}
