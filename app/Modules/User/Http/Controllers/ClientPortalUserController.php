<?php

namespace App\Modules\User\Http\Controllers;

use App\Core\User;
use App\Modules\ClientPortal\Http\Controllers\ClientPortalController;
use App\Modules\User\Http\Requests\ClientPortalUserCreateRequest;
use App\Modules\User\Http\Requests\ClientPortalUserUploadLogoRequest;
use App\Modules\User\Services\ClientPortalUserService;
use App\Modules\User\Services\UserService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

/**
 * Class UserController
 *
 * @package App\Modules\User\Http\Controllers
 */
class ClientPortalUserController extends ClientPortalController
{
    /**
     * Apply auth filter
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List users for client portal
     *
     * @param ClientPortalUserService $clientPortalUserService
     *
     * @return JsonResponse
     *
     * @throws InvalidArgumentException
     */
    public function index(Request $request, ClientPortalUserService $clientPortalUserService)
    {
        return response()->json([
            'data' => $clientPortalUserService->listPermissions($request->get('search')),
        ]);
    }

    /**
     * List distinct customers for client portal
     *
     * @param Request $request
     * @param ClientPortalUserService $clientPortalUserService
     *
     * @return JsonResponse
     */
    public function indexCustomers(Request $request, ClientPortalUserService $clientPortalUserService)
    {
        $input = $request->all();

        if (Arr::has($input, 'reportLinkAdmin')) {
            return response()->json([
                'data' => $clientPortalUserService->listCustomers(true),
            ]);
        } else {
            return response()->json([
                'data' => $clientPortalUserService->listCustomers(),
            ]);
        }
    }

    /**
     * Create user for client portal
     *
     * @param ClientPortalUserCreateRequest $request
     * @param ClientPortalUserService       $clientPortalUserService
     * @param UserService                   $userService
     *
     * @throws Exception
     *
     * @return JsonResponse
     */
    public function create(
        ClientPortalUserCreateRequest $request,
        ClientPortalUserService $clientPortalUserService,
        UserService $userService
    ) {
        $this->checkPermissions(['user.store']);
        
        $companyPersonId = $request->input('company_person_id');
        $logo = $request->file('image');

        $personId = $clientPortalUserService->createPerson($companyPersonId);

        $all = $request->all();
        $roles = json_decode($all['roles']);
        $all['roles'] = [];
        foreach ($roles as $role) {
            $all['roles'][(int)$role] = 1;
        }
        $all['person_id'] = $personId;

        $user = $userService->create($all);

        $clientPortalUserService->setClientPortalCompanyPersonId($personId);

        /** @var User $userModel */
        $userModel = $user[0];

        $userModel->company_person_id = $companyPersonId; // updated from personID - why we set company and personID ? 2020-06-12 - pkaczmarski
        $userModel->save();

        $userId = $userModel->id;

        if ($logo) {
            $logoUrl = $clientPortalUserService->storeLogoImage($logo, $companyPersonId);
        } else {
            $logoUrl = null;
        }

        return response()->json([
            'person_id' => $personId,
            'user_id'   => $userId,
            'logo_url'  => $logoUrl,
        ]);
    }

    /**
     * Upload logo to client portal
     *
     * @param ClientPortalUserUploadLogoRequest $request
     * @param ClientPortalUserService           $clientPortalUserService
     *
     * @return JsonResponse
     *
     * @throws Exception
     * @throws RuntimeException
     */
    public function uploadLogo(
        ClientPortalUserUploadLogoRequest $request,
        ClientPortalUserService $clientPortalUserService
    ) {
        $companyPersonId = $request->input('company_person_id');
        $logo = $request->file('image');

        if ($logo) {
            $file = $clientPortalUserService->storeLogoImage($logo, $companyPersonId);
        } else {
            $file = null;
        }

        return response()->json([
            'item' => $file,
        ]);
    }
}
