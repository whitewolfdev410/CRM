<?php

namespace App\Modules\Address\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Address\Repositories\StateRepository;
use Illuminate\Config\Repository as Config;
use App\Modules\Address\Http\Requests\StateRequest;
use Illuminate\Support\Facades\App;

/**
 * Class StateController
 *
 * @package App\Modules\State\Http\Controllers
 */
class StateController extends Controller
{
    /**
     * State repository
     *
     * @var StateRepository
     */
    private $stateRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param StateRepository $stateRepository
     */
    public function __construct(StateRepository $stateRepository)
    {
        $this->middleware('auth');
        $this->stateRepository = $stateRepository;
    }

    /**
     * Return list of State
     *
     * @param Config $config
     *
     * @return Response
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['state.index']);
        $onPage = $config->get('system_settings.address_state_pagination');
        $list = $this->stateRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified State
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $this->checkPermissions(['state.show']);
        $id = (int)$id;

        return response()->json($this->stateRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return Response
     */
    public function create()
    {
        $this->checkPermissions(['state.store']);
        $rules['fields'] = $this->stateRepository->getRequestRules();

        return response()->json($rules);
    }


    /**
     * Store a newly created State in storage.
     *
     * @param StateRequest $request
     *
     * @return Response
     */
    public function store(StateRequest $request)
    {
        $this->checkPermissions(['state.store']);
        $model = $this->stateRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display State and module configuration for update action
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $this->checkPermissions(['state.update']);
        $id = (int)$id;

        return response()->json($this->stateRepository->show($id, true));
    }

    /**
     * Update the specified State in storage.
     *
     * @param StateRequest $request
     * @param  int $id
     *
     * @return Response
     */
    public function update(StateRequest $request, $id)
    {
        $this->checkPermissions(['state.update']);
        $id = (int)$id;

        $record = $this->stateRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified State from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkPermissions(['state.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->stateRepository->destroy($id); */
    }
}
