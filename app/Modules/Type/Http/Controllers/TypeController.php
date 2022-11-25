<?php

namespace App\Modules\Type\Http\Controllers;

use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\Type\Http\Requests\TypeRequest;
use App\Modules\Type\Models\Type;
use App\Modules\Type\Repositories\TypeRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class TypeController
 *
 * @package App\Modules\Type\Http\Controllers
 */
class TypeController extends Controller
{
    /**
     * Type repository
     *
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param TypeRepository $typeRepository
     */
    public function __construct(TypeRepository $typeRepository)
    {
        $this->middleware('auth');
        $this->typeRepository = $typeRepository;
    }

    /**
     * Return list of types
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['type.index']);

        $onPage = $config->get('system_settings.type_pagination');

        $list = $this->typeRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified type.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function show($id)
    {
        $this->checkPermissions(['type.show']);

        $id = (int)$id;

        return response()->json($this->typeRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function create()
    {
        $this->checkPermissions(['type.store']);
        $rules['fields'] = $this->typeRepository->getRequestRules();

        return response()->json($rules);
    }

    /**
     * Store a newly created type in storage.
     *
     * @param TypeRequest $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(TypeRequest $request)
    {
        $this->checkPermissions(['type.store']);
        $model = $this->typeRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Return Type module configuration for update action
     *
     * @param int $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function edit($id)
    {
        $this->checkPermissions(['type.update']);

        $id = (int)$id;

        return response()->json($this->typeRepository->show($id, true));
    }

    /**
     * Update the specified type in storage.
     *
     * @param TypeRequest $request
     * @param int         $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function update(TypeRequest $request, $id)
    {
        $this->checkPermissions(['type.update']);

        $id = (int)$id;

        $record = $this->typeRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified type from storage.
     *
     * @param  int $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function destroy($id)
    {
        $this->checkPermissions(['type.destroy']);
        
        //TODO: disable instead of delete???

        $id = (int)$id;
        $status = $this->typeRepository->destroy($id);

        return response()->json(['data' => $status['data']], $status['code']);
    }

    /**
     * Rearranges types by order
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function rearrange(Request $request)
    {
        $this->checkPermissions(['type.update']);

        /** @var int[] $ids */
        $ids = $request->get('types');
        $order = 0;
        foreach ($ids as $id) {
            /** @var Type $type */
            $type = $this->typeRepository->find($id);
            $type->setOrderby($order);
            $type->save();

            $order++;
        }

        return response()->json([
            'result' => true,
        ]);
    }
}
