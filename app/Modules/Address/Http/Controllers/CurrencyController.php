<?php

namespace App\Modules\Address\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Address\Repositories\CurrencyRepository;
use Illuminate\Config\Repository as Config;
use App\Modules\Address\Http\Requests\CurrencyRequest;
use Illuminate\Support\Facades\App;

/**
 * Class CurrencyController
 *
 * @package App\Modules\Currency\Http\Controllers
 */
class CurrencyController extends Controller
{
    /**
     * Currency repository
     *
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param CurrencyRepository $currencyRepository
     */
    public function __construct(CurrencyRepository $currencyRepository)
    {
        $this->middleware('auth');
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * Return list of Currency
     *
     * @param Config $config
     *
     * @return Response
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['currency.index']);
        $onPage = $config->get('system_settings.address_currency_pagination');
        $list = $this->currencyRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Currency
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $this->checkPermissions(['currency.show']);
        $id = (int)$id;

        return response()->json($this->currencyRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return Response
     */
    public function create()
    {
        $this->checkPermissions(['currency.store']);
        $rules['fields'] = $this->currencyRepository->getRequestRules();

        return response()->json($rules);
    }


    /**
     * Store a newly created Currency in storage.
     *
     * @param CurrencyRequest $request
     *
     * @return Response
     */
    public function store(CurrencyRequest $request)
    {
        $this->checkPermissions(['currency.store']);
        $model = $this->currencyRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display Currency and module configuration for update action
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $this->checkPermissions(['currency.update']);
        $id = (int)$id;

        return response()->json($this->currencyRepository->show($id, true));
    }

    /**
     * Update the specified Currency in storage.
     *
     * @param CurrencyRequest $request
     * @param  int $id
     *
     * @return Response
     */
    public function update(CurrencyRequest $request, $id)
    {
        $this->checkPermissions(['currency.update']);
        $id = (int)$id;

        $record = $this->currencyRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified Currency from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkPermissions(['currency.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->currencyRepository->destroy($id); */
    }
}
