<?php

namespace App\Modules\User\Services;

use App\Modules\User\Repositories\UserDeviceHistoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class UserDeviceHistoryService
{
    /**
     * @var UserDeviceHistoryRepository
     */
    protected $repository;

    /**
     * Initialize class parameters
     *
     * @param UserDeviceHistoryRepository $repository
     */
    public function __construct(
        UserDeviceHistoryRepository $repository
    ) {
        $this->repository = $repository;
    }

    /**
     * Return assets
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return array|Collection|LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getAll(
        $perPage = 50,
        array $columns = [
            'history.*',
            'person.custom_1',
            'person.custom_3',
        ],
        array $order = []
    ) {
        return $this->repository->paginate($perPage, $columns, $order);
    }
}
