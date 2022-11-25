<?php

namespace App\Modules\Address\Http\Controllers;

use App\Modules\Address\Models\AddressEnvelope;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\Address\Http\Requests\AddressRequest;
use App\Modules\Address\Repositories\AddressRepository;
use App\Modules\Contact\Repositories\ContactRepository;
use App\Modules\Person\Repositories\LinkPersonCompanyRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class AddressController
 *
 * @package App\Modules\Address\Http\Controllers
 */
class AddressController extends Controller
{
    /**
     * Address repository
     *
     * @var AddressRepository
     */
    private $addressRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param AddressRepository $addressRepository
     */
    public function __construct(AddressRepository $addressRepository)
    {
        $this->middleware('auth');
        $this->addressRepository = $addressRepository;
    }

    /**
     * Return list of Address
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['address.index']);

        $onPage = $config->get('system_settings.address_pagination');

        $list = $this->addressRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Address
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show($id)
    {
        $this->checkPermissions(['address.show']);

        $id = (int)$id;

        return response()->json($this->addressRepository->show($id));
    }

    /**
     *  Return module configuration for store action
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['address.store']);

        $config['fields'] = $this->addressRepository->getConfig();

        return response()->json($config);
    }

    /**
     * Store a newly created Address in storage.
     *
     * @param AddressRequest              $request
     * @param ContactRepository           $contactRepository
     * @param LinkPersonCompanyRepository $personCompanyRepository
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function store(
        AddressRequest $request,
        ContactRepository $contactRepository,
        LinkPersonCompanyRepository $personCompanyRepository
    ) {
        $this->checkPermissions(['address.store']);

        [$model, $queue, $defChanged]
            = $this->addressRepository->create($request->all());

        if ($model) {
            $contactRepository->assignNotAssigned(
                $model->person_id,
                $model->address_id
            );

            $personCompanyRepository->assignNotAssigned(
                $model->person_id,
                $model->address_id
            );
        }

        $data['item'] = $model;
        $data['queue'] = $queue;
        $data['changed'] = $defChanged;

        return response()->json($data, 201);
    }


    /**
     * Return module configuration for update action
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function edit($id)
    {
        $this->checkPermissions(['contact.update']);

        $id = (int)$id;

        return response()->json($this->addressRepository->show($id, true));
    }


    /**
     * Update the specified Address in storage.
     *
     * @param AddressRequest $request
     * @param  int           $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(AddressRequest $request, $id)
    {
        $this->checkPermissions(['address.update']);

        $id = (int)$id;

        [$model, $queue, $defChanged]
            = $this->addressRepository->updateWithIdAndInput($id, $request->all());

        $data['item'] = $model;

        if ($queue) {
            $data['queue'] = $queue;
        }
        $data['changed'] = $defChanged;

        return response()->json($data);
    }

    /**
     * Remove the specified Address from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     * @throws \Exception
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['address.destroy']);

        $id = (int)$id;
        $result = $this->addressRepository->markAsDeleted($id);

        return response()->json([
            'result' => $result,
        ]);
    }

    /**
     * Verify the specified Address (adds to geocoding and display)
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function verify($id)
    {
        $this->checkPermissions(['address.verify']);

        $id = (int)$id;

        [$model, $queue] = $this->addressRepository->verify($id);

        $data['item'] = $model;
        $data['queue'] = $queue;

        return response()->json($data);
    }

    /**
     * Generates label in PDF for small dymo printer for given address
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function envelope($id)
    {
        $this->checkPermissions(['address.envelope']);

        $id = (int)$id;

        $data = $this->addressRepository->findForEnvelope($id);

        $pdf = new AddressEnvelope(
            'starter',
            'mm',
            1,
            1
        );
        $pdfContent = $pdf->generate($data);

        return response(
            $pdfContent,
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Length'      => strlen($pdfContent),
                'Content-Disposition' => 'attachment; filename="address.pdf"',
                'Cache-Control'       => 'private, max-age=0, must-revalidate',
                'Pragma'              => 'public',
            ]
        );
    }

    /**
     * Gets all the address of the customer
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function getCustomerAddresses(Config $config)
    {
        $this->checkPermissions(['address.index']);

        $customerId = $config->get('app.company_id');

        $list = $this->addressRepository->getForPerson($customerId);

        return response()->json([
            'customer_id' => $customerId,
            'items'       => $list,
        ]);
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
        $this->checkPermissions(['address.index']);

        $searchKey = $request->get('search_key');

        $columns = [
            'address.address_id',
            'address.address_1',
            'address.address_2',
            'address.city',
            'address.state',
            'address.zip_code',
            'address.address_name',
        ];

        $results = $this->addressRepository
            ->searchByKey($searchKey, $columns);

        return response()->json($results);
    }
}
