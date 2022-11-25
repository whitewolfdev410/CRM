<?php

namespace App\Modules\Person\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Person\Http\Requests\LinkPersonCompanyRequest;
use App\Modules\Person\Repositories\LinkPersonCompanyRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Class LinkPersonCompanyController
 *
 * @package App\Modules\Person\Http\Controllers
 */
class LinkPersonCompanyController extends Controller
{
    /**
     * PersonCompany repository
     *
     * @var LinkPersonCompanyRepository
     */
    private $personCompanyRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param LinkPersonCompanyRepository $personCompanyRepository
     */
    public function __construct(
        LinkPersonCompanyRepository $personCompanyRepository
    ) {
        $this->middleware('auth');
        $this->personCompanyRepository = $personCompanyRepository;
    }

    /**
     * Return list of connections
     *
     * @param Config $config
     *
     * @return Response
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['link-person-company.index']);

        $onPage
            = $config->get('system_settings.person_link_person_company_pagination');

        $list = $this->personCompanyRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified connection
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $this->checkPermissions(['link-person-company.show']);
        $id = (int)$id;

        return response()->json($this->personCompanyRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return Response
     */
    public function create()
    {
        $this->checkPermissions(['link-person-company.store']);
        $rules['fields'] = $this->personCompanyRepository->getRequestRules();

        return response()->json($rules);
    }

    /**
     * Store a newly created connection in storage.
     *
     * @param LinkPersonCompanyRequest $request
     *
     * @return Response
     */
    public function store(LinkPersonCompanyRequest $request)
    {
        $this->checkPermissions(['link-person-company.store']);
        list($model, $defChanged) = $this->personCompanyRepository->create($request->all());

        $data['item'] = $model;
        $data['changed'] = $defChanged;

        return response()->json($data, 201);
    }

    /**
     * Return Person module configuration for update action
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $this->checkPermissions(['link-person-company.update']);

        $id = (int)$id;

        return response()->json($this->personCompanyRepository->show(
            $id,
            true
        ));
    }

    /**
     * Update the specified Person in storage.
     *
     * @param LinkPersonCompanyRequest $request
     * @param int $id
     *
     * @return Response
     */
    public function update(LinkPersonCompanyRequest $request, $id)
    {
        $this->checkPermissions(['link-person-company.update']);
        $id = (int)$id;
        list($model, $defChanged) = $this->personCompanyRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );
        
        $data = $this->personCompanyRepository->show($id);
        $data['changed'] = $defChanged;

        return response()->json($data);
    }

    /**
     * Remove the specified Person from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkPermissions(['link-person-company.destroy']);

        return $this->personCompanyRepository->destroy($id);
    }

    /**
     * @param Request $request
     * @param $companyId
     * @param $clientId
     * @return \Illuminate\Http\JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function linkClientPortalUser(Request $request, $companyId, $clientId)
    {
        $this->checkPermissions(['link-person-company.link-client-portal-users']);

        $input = $request->all();

        if (Arr::has($input, 'link')) {
            $status = $this->personCompanyRepository->linkUnlinkClientPortalUser($companyId, $clientId, $input['link']);
            if ($status['code'] == 200) {
                return response()->json(['success' => $status['success'], 'message' => $status['message']], $status['code']);
            } else {
                return response()->json(['success' => $status['success'], 'data' => $status['data']], $status['code']);
            }
        } else {
            return response()->json(['success' => false, 'data' => ['error' => ['message' => 'Link param is missing.']]], 422);
        }
    }

    /**
     * @param $person_id
     * @return \Illuminate\Http\JsonResponse
     * @throws App\Core\Exceptions\NoPermissionException
     */
    public function getLinkedList($person_id)
    {
        $this->checkPermissions(['link-person-company.show']);
        $list = $this->personCompanyRepository->getLinkedList($person_id);
        return response()->json($list);
    }
}
