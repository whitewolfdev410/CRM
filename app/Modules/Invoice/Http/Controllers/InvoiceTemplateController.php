<?php

namespace App\Modules\Invoice\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\NoPermissionException;
use App\Http\Controllers\Controller;
use App\Modules\Invoice\Http\Requests\InvoiceTemplateRequest;
use App\Modules\Invoice\Services\InvoiceTemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class InvoiceController
 *
 * @package App\Modules\Invoice\Http\Controllers
 */
class InvoiceTemplateController extends Controller
{
    /**
     * Invoice repository
     *
     * @var InvoiceTemplateService
     */
    private $invoiceTemplateService;

    /**
     * Set repository and apply auth filter
     *
     * @param  InvoiceTemplateService  $invoiceTemplateService
     */
    public function __construct(InvoiceTemplateService $invoiceTemplateService)
    {
        $this->middleware('auth');

        $this->invoiceTemplateService = $invoiceTemplateService;
    }

    /**
     * Return list of InvoiceTemplate
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function index()
    {
        $this->checkPermissions(['invoice.template-index']);

        $list = $this->invoiceTemplateService->paginate();

        return response()->json($list);
    }

    /**
     * Display the specified InvoiceTemplate
     *
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function show(int $id)
    {
        $this->checkPermissions(['invoice.template-show']);

        $result = $this->invoiceTemplateService->show($id);

        return response()->json($result);
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
        $this->checkPermissions(['invoice.template-store']);

        $rules = $this->invoiceTemplateService->getRequestRules();

        return response()->json($rules);
    }

    /**
     * Store a newly created InvoiceTemplate in storage.
     *
     * @param  InvoiceTemplateRequest  $invoiceTemplateRequest
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function store(InvoiceTemplateRequest $invoiceTemplateRequest)
    {
        $this->checkPermissions(['invoice.template-store']);

        $result = $this->invoiceTemplateService->save($invoiceTemplateRequest, null);

        return response()->json($result, 200);
    }

    /**
     * Display InvoiceTemplate and module configuration for update action
     *
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws ModelNotFoundException
     * @throws NoPermissionException
     */
    public function edit(int $id)
    {
        $this->checkPermissions(['invoice.template-update']);

        $model = $this->invoiceTemplateService->edit($id);

        return response()->json($model);
    }

    /**
     * Update the specified InvoiceTemplate in storage.
     *
     * @param  InvoiceTemplateRequest  $invoiceTemplateRequest
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws NoPermissionException
     */
    public function update(InvoiceTemplateRequest $invoiceTemplateRequest, int $id)
    {
        $this->checkPermissions(['invoice.template-update']);

        $result = $this->invoiceTemplateService->save($invoiceTemplateRequest, $id);

        return response()->json($result, 200);
    }

    /**
     * Remove the specified Invoice from storage.
     *
     * @param  int  $id
     *
     * @return JsonResponse
     *
     * @throws HttpException
     * @throws NoPermissionException
     */
    public function destroy(int $id)
    {
        $this->checkPermissions(['invoice.template-destroy']);

        //$this->invoiceTemplateService->destroy($id);

        return response()->json([], 404);
    }
}
