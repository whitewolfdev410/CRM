<?php

namespace App\Modules\Person\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Core\InputFormatter;
use App\Http\Controllers\Controller;
use App\Modules\Address\Http\Requests\AddressRequest;
use App\Modules\Address\Models\Address;
use App\Modules\Address\Models\LinkVendorAddress;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\Contact\Http\Requests\ContactRequest;
use App\Modules\Person\Http\Requests\AssignVendorAddressRequest;
use App\Modules\Person\Http\Requests\CheckCompanyRequest;
use App\Modules\Person\Http\Requests\CompanyStoreComplexRequest;
use App\Modules\Person\Http\Requests\CompanyStoreRequest;
use App\Modules\Person\Http\Requests\CompanyUpdateRequest;
use App\Modules\Person\Http\Requests\PersonNoteStoreRequest;
use App\Modules\Person\Models\CompanyMenu;
use App\Modules\Person\Repositories\BillingCompanySettingsRepository;
use App\Modules\Person\Repositories\CompanyRepository;
use App\Modules\Person\Repositories\PersonNoteRepository;
use App\Modules\Person\Repositories\PersonRepository;
use App\Modules\Person\Services\CompanyComplexValidatorService;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class CompanyController
 *
 * @package App\Modules\Person\Http\Controllers
 */
class CompanyController extends Controller
{
    /**
     * Company repository
     *
     * @var CompanyRepository
     */
    private $companyRepository;
    
    /**
     * @var PersonNoteRepository
     */
    private $personNoteRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param  CompanyRepository  $companyRepository
     * @param  PersonNoteRepository  $personNoteRepository
     */
    public function __construct(CompanyRepository $companyRepository, PersonNoteRepository $personNoteRepository)
    {
        $this->middleware('auth');
        $this->companyRepository = $companyRepository;
        $this->personNoteRepository = $personNoteRepository;
    }

    /**
     * Return list of Company
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws NoPermissionException
     */
    public function index(Config $config, Request $request)
    {
        $this->checkPermissions(['company.index']);

        $onPage = $config->get('system_settings.person_company_pagination');

        $list = $this->companyRepository->babayaga25($onPage);

        $addFields = $request->input('with_fields', 1);
        if ($addFields) {
            $list = $list->toArray();
            $list['fields'] = $this->companyRepository->getLabels('index');
        }

        return response()->json($list);
    }

    /**
     * Display the specified Company
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function show($id)
    {
        $this->checkPermissions(['company.show']);
        $id = (int)$id;
        $data = $this->companyRepository->show($id);

        return response()->json($data);
    }

    /**
     *  Return Company module configuration for store action
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['company.store']);
        $data = $this->companyRepository->getDatasetData('store');

        return response()->json($data);
    }

    /**
     * Store a newly created Company in storage.
     *
     * @param CompanyStoreRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws NoPermissionException
     */
    public function store(CompanyStoreRequest $request)
    {
        $this->checkPermissions(['company.store']);
        list($model, $groups) = $this->companyRepository->create($request->all());

        $data['item'] = $model;
        $data['groups'] = $groups;

        return response()->json($data, 201);
    }

    /**
     * Store a newly created Person together with addresses and contacts
     * in storage.
     *
     * @param CompanyStoreComplexRequest $request
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws NoPermissionException
     */
    public function storeComplex(CompanyStoreComplexRequest $request)
    {
        $this->checkPermissions(['company.store.complex']);

        $validator = new CompanyComplexValidatorService(
            $request,
            new AddressRequest(),
            new ContactRequest(),
            new InputFormatter()
        );

        $validator->validate();

        list($model, $groups, $queues)
            = $this->companyRepository->createComplex($validator->getFormattedData());

        $data['item'] = $model;
        $data['groups'] = $groups;
        $data['address_queues'] = $queues;

        return response()->json($data, 201);
    }

    /**
     * Return Company module configuration for update action
     *
     * @param int     $id
     * @param Request $request
     * @param Config  $config
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function edit($id, Request $request, Config $config)
    {
        $this->checkPermissions(['company.update']);
        $id = (int)$id;

        $data = $this->companyRepository->show($id, true);
        $menu = $this->getMenu($config);
        if ($menu !== false) {
            $data['menu'] = $menu;
        }

        return response()->json($data);
    }

    /**
     * Update the specified Company in storage.
     *
     * @param CompanyUpdateRequest $request
     * @param int                  $id
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function update(CompanyUpdateRequest $request, $id)
    {
        $this->checkPermissions(['company.update']);
        $id = (int)$id;
        list($model, $groups) = $this->companyRepository
            ->updateWithIdAndInput($id, $request->all());

        $data['item'] = $model;
        $data['groups'] = $groups;

        return response()->json($data);
    }

    /**
     * Remove the specified Company from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws HttpException
     * @throws NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['company.destroy']);

        abort(404);
        exit;

        /*        $id = (int)$id;
                $this->companyRepository->destroy($id);*/
    }

    //region Address

    /**
     * Assign a vendor to address
     *
     * @param AssignVendorAddressRequest $request
     * @param int                        $addressId
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function assignVendorsToAddress(
        AssignVendorAddressRequest $request,
        $addressId
    ) {
        $this->checkPermissions(['company.address-vendor-store']);

        $rank = $this->sortVendorsAtAddress($addressId);

        LinkVendorAddress::create([
            'address_id'       => $addressId,
            'rank'             => $rank,
            'trade_type_id'    => $request->get('trade_type_id'),
            'vendor_person_id' => $request->get('vendor_person_id'),
        ]);

        return $this->queryVendorsByAddress($addressId, null);
    }

    /**
     * Rearranges vendors of an address
     *
     * @param Request $request
     * @param int     $addressId
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function rearrangeVendorsAtAddress(
        Request $request,
        $addressId
    ) {
        $this->checkPermissions(['company.address-vendor-store']);

        /** @var int[] $ids */
        $ids = $request->get('vendors');
        $rank = 0;
        foreach ($ids as $id) {
            /** @var LinkVendorAddress $linkedVendor */
            $linkedVendor = LinkVendorAddress::find($id);
            $linkedVendor->setRank($rank);
            $linkedVendor->save();

            $rank++;
        }

        return $this->queryVendorsByAddress($addressId, null);
    }

    /**
     * Assign a vendor to address
     *
     * @param Request $request
     * @param int     $addressId
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function unassignVendorsToAddress(
        Request $request,
        $addressId
    ) {
        $this->checkPermissions(['company.address-vendor-destroy']);

        LinkVendorAddress
            ::find($request->get('vendor_id'))
            ->delete();

        $rank = $this->sortVendorsAtAddress($addressId);

        return $this->queryVendorsByAddress($addressId, null);
    }

    /**
     * Return list of vendors for address
     *
     * @param Request $request
     * @param int     $addressId
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function getVendorsByAddress(
        Request $request,
        $addressId
    ) {
        $this->checkPermissions(['company.address-vendor-index']);

        $tradeTypeId = $request->get('trade_type_id');

        return $this->queryVendorsByAddress($addressId, $tradeTypeId);
    }

    private function queryVendorsByAddress($addressId, $tradeTypeId)
    {
        /** @var Address $model */
        $model = Address
            ::with('linkedVendors')
            ->find($addressId)
            ->linkedVendors()
            ->with('vendor')
            ->with('tradeType')
            ->orderBy('rank');

        if ($tradeTypeId) {
            $model = $model->where('trade_type_id', '=', $tradeTypeId);
        }

        /** @type LinkVendorAddress[] $linkedVendors */
        $linkedVendors = $model->get();

        $items = [];
        foreach ($linkedVendors as $linkedVendor) {
            $item = $linkedVendor->toArray();

            /** @var App\Modules\Type\Models\Type $vendor */
            $trade_type = $linkedVendor->getTradeType();
            /** @var App\Modules\Person\Models\Person $vendor */
            $vendor = $linkedVendor->getVendor();

            $item['person_name'] = $vendor->getName();
            $item['trade_type'] = $trade_type ? $trade_type->getTypeValue() : '';

            unset($item['vendor']);

            $items[] = $item;
        }

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Rearrange vendors of an address
     *
     * @param int $addressId
     *
     * @return int
     */
    private function sortVendorsAtAddress($addressId)
    {
        /** @type LinkVendorAddress[] $linkedVendors */
        $linkedVendors = Address
            ::with('linkedVendors')
            ->find($addressId)
            ->linkedVendors()
            ->orderBy('rank')
            ->get();

        $rank = 0;
        foreach ($linkedVendors as $linkedVendor) {
            $linkedVendor->setRank($rank);
            $linkedVendor->save();

            $rank++;
        }

        return $rank;
    }

    //endregion

    //region Owner


    public function getOwners(PersonRepository $personRepository)
    {
        $this->checkPermissions(['company.owner']);
        
        $list = $personRepository->getOwners('company.owner');

        return response()->json($list);
    }
    
    /**
     * Return Owner module configuration for update action
     *
     * @param AddressRepository $addressRepository
     * @param Config            $config
     * @param Request           $request
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function editOwner(
        AddressRepository $addressRepository,
        Config $config,
        Request $request
    ) {
        $this->checkPermissions(['address.index']);
        
        try {
            $this->checkPermissions(['company.owner-update']);
        } catch (NoPermissionException $e) {
            $this->checkPermissions(['owner.update']);
        }

        $id = (int)$config->get('app.company_id');

        $data = $this->companyRepository->show($id, true);
        $menu = $this->getMenu($config);
        if ($menu !== false) {
            $data['menu'] = $menu;
        }

        $addresses = $addressRepository->getForPerson($id);
        if ($addresses !== false) {
            $data['addresses'] = $addresses;
        }

        return response()->json($data);
    }

    /**
     * Update the Owner in storage.
     *
     * @param CompanyUpdateRequest $request
     * @param Config               $config
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function updateOwner(
        CompanyUpdateRequest $request,
        Config $config
    ) {
        try {
            $this->checkPermissions(['company.owner-update']);
        } catch (NoPermissionException $e) {
            $this->checkPermissions(['owner.update']);
        }

        $id = (int)$config->get('app.company_id');

        list($model, $groups) = $this->companyRepository
            ->updateWithIdAndInput($id, $request->all());

        $data['item'] = $model;
        $data['groups'] = $groups;

        return response()->json($data);
    }

    //endregion\

    /**
     * Remove select
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     */
    public function remoteSelect(Request $request)
    {
        $searchValue = $request->input('search_value', '');
        $useSLRecords = $request->input('use_sl_records', false);

        return response()->json([
            'data' => $this->companyRepository->remoteSelect($searchValue, $useSLRecords),
        ]);
    }


    public function checkIfCompanyHasBillingCompany(
        CheckCompanyRequest $checkCompanyRequest,
        BillingCompanySettingsRepository $billingCompanySettingsRepository
    ) {
        $status = $billingCompanySettingsRepository->checkIfCompanyHasBillingCompany(
            $checkCompanyRequest->get('company_id'),
            $checkCompanyRequest->get('billing_company_id')
        );

        return response()->json(['status' => $status]);
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
        $menu = $config->get('modconfig.company.menu');
        $menuItems = $this->getPermissionsStatus($menu);

        $menuBuilder = new CompanyMenu($menuItems, $menu);

        return $menuBuilder->get();
    }



    /**
     * Return list of Companies for work order
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     */
    public function getWoCompanies(Request $request)
    {
        $typeKey = $request->input('type_key', 'company.customer');
        $typeKeyID = getTypeIdByKey($typeKey);
        if ($typeKeyID > 0) {
            $sql = "SELECT person_id, custom_1 from person where kind ='company' and type_id = ".$typeKeyID." ORDER by custom_1 asc;";
            $list = DB::select(DB::raw($sql));
            $list2 = [];

            foreach ($list as $k => $l) {
                $list2[] = ['label' => $l->custom_1, 'value' => $l->person_id ];
            }
        }
        return response()->json($list2);
    }

    /**
     * @param $companyId
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getClientPortalPersons($companyId)
    {
        $this->checkPermissions(['company.show']);

        $data = $this->companyRepository->getClientPortalPersons($companyId);

        return response()->json(["data"=>$data]);
    }

    /**
     * @param $companyId
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function getAlertNotes($companyId)
    {
        $this->checkPermissions(['company.alert-note-index']);

        $data = $this->personNoteRepository->getNotes($companyId);

        return response()->json(["data" => $data]);
    }

    /**
     * @param  PersonNoteStoreRequest  $personNoteRequest
     * @param $companyId
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function addAlertNotes(PersonNoteStoreRequest $personNoteRequest, $companyId)
    {
        $this->checkPermissions(['company.alert-note-store']);

        $data = $this->personNoteRepository->create([
            'person_id'  => $companyId,
            'note'       => $personNoteRequest->get('note'),
            'created_by' => Auth::user()->getPersonId()
        ]);
        
        return response()->json(["data" => $this->personNoteRepository->show($data->getId())]);
    }

    /**
     * @param $companyId
     * @param $noteId
     *
     * @return JsonResponse
     * @throws NoPermissionException
     */
    public function deleteAlertNotes($companyId, $noteId)
    {
        $this->checkPermissions(['company.alert-note-destroy']);

        $data = $this->personNoteRepository->delete($companyId, $noteId);

        return response()->json(["status" => (bool)$data]);
    }
}
