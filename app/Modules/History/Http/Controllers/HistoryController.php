<?php

namespace App\Modules\History\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\History\Repositories\HistoryRepository;
use Illuminate\Config\Repository as Config;
use Illuminate\Http\JsonResponse;

/**
 * Class HistoryController
 *
 * @package App\Modules\History\Http\Controllers
 */
class HistoryController extends Controller
{
    /**
     * History repository
     *
     * @var HistoryRepository
     */
    private $historyRepository;

    /**
     * Set repository and apply auth filter
     *
     * @param HistoryRepository $historyRepository
     */
    public function __construct(HistoryRepository $historyRepository)
    {
        $this->middleware('auth');
        $this->historyRepository = $historyRepository;
    }

    /**
     * Return list of History
     *
     * @param Config $config
     *
     * @return JsonResponse
     *
     * @throws \App\Core\Exceptions\NoPermissionException
     */
    public function index(Config $config)
    {
        $this->checkPermissions(['history.index']);
        $onPage = $config->get('system_settings.history_pagination');
        $list = $this->historyRepository->paginate($onPage);

        return response()->json($list);
    }
}
