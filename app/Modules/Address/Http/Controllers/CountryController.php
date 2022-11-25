<?php

namespace App\Modules\Address\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Address\Repositories\CountryRepository;
use Illuminate\Config\Repository as Config;
use App\Modules\Address\Http\Requests\CountryRequest;
use Illuminate\Support\Facades\App;

/**
 * Class CountryController
 *
 * @package App\Modules\Country\Http\Controllers
 */
class CountryController extends Controller
{
    /**
     * Country repository
     *
     * @var CountryRepository
     */
    private $countryRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param CountryRepository $countryRepository
     */
    public function __construct(CountryRepository $countryRepository)
    {
        $this->middleware('auth');
        $this->countryRepository = $countryRepository;
    }

    /**
     * Return list of Country
     *
     * @param Config $config
     *
     * @return Response
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['country.index']);
        $onPage = $config->get('system_settings.address_country_pagination');
        $list = $this->countryRepository->paginate($onPage);

        return response()->json($list);
    }

    /**
     * Display the specified Country
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $this->checkPermissions(['country.show']);
        $id = (int)$id;

        return response()->json($this->countryRepository->show($id));
    }

    /**
     * Return module configuration for store action
     *
     * @return Response
     */
    public function create()
    {
        $this->checkPermissions(['country.store']);
        $rules['fields'] = $this->countryRepository->getRequestRules();

        return response()->json($rules);
    }


    /**
     * Store a newly created Country in storage.
     *
     * @param CountryRequest $request
     *
     * @return Response
     */
    public function store(CountryRequest $request)
    {
        $this->checkPermissions(['country.store']);
        $model = $this->countryRepository->create($request->all());

        return response()->json(['item' => $model], 201);
    }

    /**
     * Display Country and module configuration for update action
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $this->checkPermissions(['country.update']);
        $id = (int)$id;

        return response()->json($this->countryRepository->show($id, true));
    }

    /**
     * Update the specified Country in storage.
     *
     * @param CountryRequest $request
     * @param  int $id
     *
     * @return Response
     */
    public function update(CountryRequest $request, $id)
    {
        $this->checkPermissions(['country.update']);
        $id = (int)$id;

        $record = $this->countryRepository->updateWithIdAndInput(
            $id,
            $request->all()
        );

        return response()->json(['item' => $record]);
    }

    /**
     * Remove the specified Country from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkPermissions(['country.destroy']);
        abort(404);
        exit;

        /* $id = (int) $id;
        $this->countryRepository->destroy($id); */
    }
}
