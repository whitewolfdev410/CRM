<?php

namespace App\Modules\Invoice\Http\Controllers;

use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Modules\Invoice\Repositories\InvoiceImportExceptionRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Class InvoiceImportExceptionController
 *
 * @package App\Modules\Invoice\Http\Controllers
 */
class InvoiceImportExceptionController extends Controller
{
    /**
     * Invoice import exceptions repository
     *
     * @var InvoiceImportExceptionRepository
     */
    private $invoiceImportExceptionRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param InvoiceImportExceptionRepository $invoiceImportExceptionRepository
     */
    public function __construct(InvoiceImportExceptionRepository $invoiceImportExceptionRepository)
    {
        $this->middleware('auth');
        $this->invoiceImportExceptionRepository = $invoiceImportExceptionRepository;
    }

    /**
     * Return list of Invoice import exceptions
     *
     * @param Config  $config
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws App\Core\Exceptions\NoPermissionException
     * @throws \InvalidArgumentException
     */
    public function index(
        Config $config,
        Request $request
    ) {
        $this->checkPermissions(['invoice.index']);

        $onPage = (int)$request->get('limit', $config->get('system_settings.invoice_pagination'));

        $list = $this->invoiceImportExceptionRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Resolve import exception
     * @param  Request $request
     * @return JsonResponse
     */
    public function resolve(Request $request)
    {
        $result = $this->invoiceImportExceptionRepository->resolve($request->get('id'));

        return response()->json($result);
    }

    /**
     * Reopen import exception
     * @param  Request $request
     * @return JsonResponse
     */
    public function reopen(Request $request)
    {
        $result = $this->invoiceImportExceptionRepository->reopen($request->get('id'));

        return response()->json($result);
    }
}
