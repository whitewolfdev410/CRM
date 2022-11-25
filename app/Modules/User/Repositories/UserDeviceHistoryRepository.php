<?php

namespace App\Modules\User\Repositories;

use App\Core\AbstractRepository;
use App\Modules\History\Models\History;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * History repository class
 */
class UserDeviceHistoryRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param History   $token
     */
    public function __construct(Container $app, History $token)
    {
        parent::__construct($app, $token);
    }

    /**
     * Pagination of results
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return Collection|Paginator
     *
     * @throws InvalidArgumentException
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        /** @var Builder|History|Object $model */
        $model = $this->getModel();

        $model = $model
            ->leftJoin(
                'person',
                'history.person_id',
                '=',
                'person.person_id'
            )
            ->leftJoin(
                'user_devices',
                'history.record_id',
                '=',
                'user_devices.id'
            );
        $model = $model
            ->where('history.tablename', 'user_devices')
            ->where(function ($query) {
                $userId = $this->request->get('user_id', 0);

                /** @var Builder $query */
                $query
                    ->where('user_devices.user_id', $userId)
                    ->orWhere(function ($query) use ($userId) {
                        /** @var Builder $query */
                        $query
                            ->where('history.related_tablename', '=', 'users')
                            ->where('history.related_record_id', '=', $userId);
                    });

                $personId = $this->request->get('person_id', 0);

                if ($personId) {
                    $query->orWhere(function ($query) use ($personId) {
                        /** @var Builder $query */
                        $query
                            ->where('history.related_tablename', '=', 'person')
                            ->where('history.related_record_id', '=', $personId);
                    });
                }
            })
            ->orderByDesc('history.date_created');

        $this->setWorkingModel($model);

        return parent::paginate($perPage, $columns, $order);
    }
}
