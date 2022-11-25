<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Core\User;
use App\Modules\WorkOrder\Exceptions\LpWoResolveFailedException;
use App\Modules\WorkOrder\Models\Exceptions;
use App\Modules\WorkOrder\Models\LinkPersonWoStats;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * LinkPersonWoStats repository class
 */
class LinkPersonWoStatsRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'date',
        'service_tech_name',
        'zone',

        'lpws.created_at',

        'pd.data_value',

        't1.type_value',

        'wo.work_order_number',
    ];

    /**
     * {@inheritdoc}
     */
    protected $sortable = [
        'date',
        'service_tech_name',
        'zone',

        'lpws.created_at',

        'pd.data_value',

        't1.type_value',

        'wo.work_order_number',
    ];

    /** @type Exceptions */
    protected $exceptions;

    /**
     * Repository constructor
     *
     * @param Container         $app
     * @param LinkPersonWoStats $linkPersonWo
     * @param Exceptions        $exceptions
     */
    public function __construct(
        Container $app,
        LinkPersonWoStats $linkPersonWo,
        Exceptions $exceptions
    ) {
        parent::__construct($app, $linkPersonWo);
        $this->exceptions = $exceptions;
    }

    /**
     * Return the paginated resolved work orders
     *
     * @return Collection|LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getResolvedWO()
    {
        return $this->getWOWithViolationsNew(true); //$this->getWOWithViolations(false);
    }

    /**
     * Return the paginated unresolved work orders
     *
     * @return Collection|LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getUnresolvedWO()
    {
        return $this->getWOWithViolationsNew(false); //$this->getWOWithViolations(false);
    }

    /**
     * Return the paginated work orders with violations
     *
     * @param bool $resolved
     *
     * @return Collection|LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getWOWithViolations($resolved = false)
    {
        $input = $this->getInput();
        $columns = [
            'lpws.sch_time',
            'lpws.duration_time',
            "link_person_wo_stats_id AS 'id'",
            "pd.data_value as 'team'",
            "`zone` AS 'route'",
            "person_name(lpws.person_id) AS 'service_tech_name'",
            '(' .
            '   SELECT number FROM user_devices ' .
            '   WHERE user_id = (' .
            '      SELECT id FROM users WHERE person_id = lpws.person_id LIMIT 1' .
            '   ) AND active=1 LIMIT 1' .
            ") AS 'tech_cell_number'",
            "a.address_name AS 'site_id'",
            "person_name(wo.company_person_id) AS 'customer'",
            "a.address_1 AS 'address_1'",
            "CONCAT_WS(' ', a.city, UPPER(a.state), a.zip_code) AS 'address_2'",
            "a.latitude AS 'latitude'",
            "a.longitude AS 'longitude'",
            "wo.work_order_number AS 'otr_number'",
            'lpws.resolution_type_id',
            'lpws.resolution_memo',
            "'' AS 'violation'",
            'lpws.created_at as date'
        ];

        $type_completed = getTypeIdByKey('tech_status.completed');
        $type_wip = getTypeIdByKey('tech_status.wip');

        /** @var Builder|Model|Object $model */
        $model = DB::table($this->getModel()->getTable() . ' AS lpws');
        $model
            ->leftJoin(
                'work_order AS wo',
                'wo.work_order_id',
                '=',
                'lpws.work_order_id'
            )
            ->leftJoin(
                'address AS a',
                'a.address_id',
                '=',
                'wo.shop_address_id'
            )
            ->leftJoin('person_data AS pd', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('pd.person_id', '=', 'lpws.person_id')
                    ->where('pd.data_key', '=', 'team');
            })
            ->whereIn('lpws.tech_status_type_id', [
                $type_completed,
                $type_wip,
            ])
            ->where('lpws.is_resolved', $resolved ? '=' : '<>', 1)
            ->where(function ($query) {
                /** @var Builder $query */
                $query
                    ->whereRaw('(sch_time * 1.35) < duration_time')
                    ->orWhereRaw('(sch_time * 0.65) > duration_time');
            })
            ->where('duration_time', '<>', 0)
            ->where('duration', '<>', '00:00')
            ->where('duration', '<>', '0:00')
            ->where('sch_time', '<>', 0);

        if (!empty($input['search_term'])) {
            $searchTerm = trim($input['search_term']);

            if (!empty($searchTerm)) {
                $searchTerm = preg_replace('/[\t\n\r\0\x0B]/', '', $searchTerm);
                $searchTerm = preg_replace('/([\s])\1+/', ' ', $searchTerm);
                $searchRegex = implode('|', explode(' ', $searchTerm));

                $searchParams = ["%$searchTerm%"];

                $countModel = clone $model;
                $countModel
                    ->where(function ($query) use ($searchParams, $searchRegex) {
                        /** @var Builder $query */
                        $query
                            ->whereRaw('person_name(lpws.person_id) LIKE ?', $searchParams)
                            ->orWhereRaw('pd.data_value REGEXP ?', [$searchRegex])
                            ->orWhereRaw('lpws.zone LIKE ?', $searchParams)
                            ->orWhereRaw('person_name(wo.company_person_id) LIKE ?', $searchParams)
//                            ->orWhereRaw("CONCAT_WS(' ', a.address_1, a.city, UPPER(a.state), a.zip_code) REGEXP ?", [$searchRegex]);
                            ->orWhereRaw('a.address_name LIKE ?', $searchParams);
                    });
                $this->setCountModel($countModel);

                $model
                    ->havingRaw('service_tech_name LIKE ?', $searchParams)
                    ->orHavingRaw('team REGEXP ?', [$searchRegex])
                    ->orHavingRaw('route LIKE ?', $searchParams)
                    ->orHavingRaw('customer LIKE ?', $searchParams)
//                    ->orHavingRaw("CONCAT_WS(' ', address_1, address_2) REGEXP ?", [$searchRegex]);
                    ->orWhereRaw('site_id LIKE ?', $searchParams);
            }
        }

        //print_r($model->toSql());
        $this->setRawColumns(true);
        $this->setWorkingModel($model);

        $data = parent::paginate(50, $columns, [
            'lpws.date'                    => 'DESC',
            'lpws.link_person_wo_stats_id' => 'DESC',
        ]);

        $this->clearWorkingModel();

        $data->each(function (&$item) {
            if ($item->sch_time * 1.35 < $item->duration_time) {
                $item->violation = 'ACT > SCH 35%';
            } elseif ($item->sch_time * 0.65 > $item->duration_time) {
                $item->violation = 'ACT < SCH 35%';
            }

            /*if ($item->violation == '') {
                //Remove
            }*/
        });

        return $data;
    }

    /**
     * Resolve
     *
     * @param $id
     * @param $resolutionType
     * @param $resolutionMemo
     *
     * @throws LpWoResolveFailedException
     * @throws ModelNotFoundException
     */
    public function resolve($id, $resolutionType, $resolutionMemo)
    {
        /** @var Exceptions $model */
        $model = $this->exceptions->findOrFail($id);
        $model
            ->setIsResolved(1)
            ->setResolutionTypeId($resolutionType)
            ->setResolutionMemo($resolutionMemo);

        /** @var User $currentUser */
        $currentUser = Auth::user();
        if ($currentUser) {
            $model->setResolvingUser($currentUser->getId());
        }

        $model->save();

        $model = $this->exceptions->findOrFail($id);
        if (!$model->getIsResolved()) {
            /** @var LpWoResolveFailedException $exception */
            $exception = $this->app->make(LpWoResolveFailedException::class);
            throw $exception;
        }
    }

    /**
     * Return the paginated work orders with violations
     *
     * @param bool $resolved
     *
     * @return Collection|LengthAwarePaginator
     *
     * @throws InvalidArgumentException
     */
    public function getWOWithViolationsNew($resolved = false)
    {
        $input = $this->getInput();
        // selecting all work order whre there is some issue
        $columns = [
            "exs.exception_id AS 'id'",
            "IFNULL(person_name(lpw.person_id),person_name(exs.record_id)) AS 'service_tech_name'",
            "IFNULL((SELECT number FROM user_devices WHERE user_id = (SELECT id FROM users WHERE person_id = lpw.person_id LIMIT 1) AND active=1 LIMIT 1), '') AS 'tech_cell_number'",
            "IFNULL((SELECT zone FROM link_person_wo_stats lpws WHERE lpws.link_person_wo_id = lpw.link_person_wo_id AND lpws.`date` = exs.date AND zone <> '' LIMIT 1),IFNULL((SELECT route FROM daily_inspections di LEFT JOIN work_order_live_action wola2 ON di.vehicle_number = wola2.vehicle_number WHERE route <> '' AND wola2.work_order_live_action_id = exs.related_record_id ORDER BY wola2.created_at, di.created_at DESC LIMIT 1), ''))  AS 'route'",
            "person_name(wo.company_person_id) AS 'wo_company'",
            "pd.`data_value`  AS 'team'",
            "wo.fin_loc  AS 'site_id'",
            "person_name(wo.company_person_id)  AS 'customer'",
            "a.address_1  AS 'address_1'",
            "CONCAT_WS(' ', a.city, UPPER(a.state), a.zip_code)  AS 'address_2'",
            "a.latitude  AS 'latitude'",
            "a.longitude  AS 'longitude'",
            "wo.work_order_number  AS 'otr_number'",
            "wo.work_order_id  AS 'work_order_id'",
            "exs.description AS 'violation'",
            "exs.created_at AS 'date'",
            "IFNULL((SELECT pd2.data_value FROM person_data pd2 WHERE pd2.person_id = lpw.`person_id` AND pd2.data_key = 'region' LIMIT 1), '') AS 'region'",
            "exs.title",
            "exs.is_resolved",
            "t1.type_value AS resolution_type",
            "exs.resolution_memo",
            "person_name(resolving_person.person_id) as resolving_person",
        ];

        $model = DB::table('exceptions AS exs');
        $model
            ->leftJoin(
                'link_person_wo AS lpw',
                'lpw.link_person_wo_id',
                '=',
                'exs.link_person_wo_id'
            )
            ->leftJoin(
                'work_order AS wo',
                'wo.work_order_id',
                '=',
                'lpw.work_order_id'
            )
            ->leftJoin(
                'address AS a',
                'a.address_id',
                '=',
                'wo.shop_address_id'
            )
            ->leftJoin(
                'type AS t1',
                't1.type_id',
                '=',
                'exs.resolution_type_id'
            )
            ->leftJoin('person_data AS pd', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('pd.person_id', '=', 'lpw.person_id')
                    ->where('pd.data_key', '=', 'team');
            })
            ->leftJoin('users AS resolving_user', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('exs.resolving_user_id', '=', 'resolving_user.id');
            })
            ->leftJoin('person AS resolving_person', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('resolving_user.person_id', '=', 'resolving_person.person_id');
            })
            ->leftJoin('person AS lpwo_person', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('lpwo_person.person_id', '=', 'lpw.person_id');
            })
            ->leftJoin('person AS wo_person', function ($joinClause) {
                /** @var JoinClause $joinClause */
                $joinClause
                    ->on('wo_person.person_id', '=', 'wo.company_person_id');
            })
            ->where('exs.is_resolved', $resolved ? '=' : '<>', 1)
            ->where('exs.created_at', '>', '2018-05-04 00:00:00')
            ->whereRaw("(exs.title = 'Below the schedule' OR exs.title = 'Above the schedule' OR exs.title = 'Out of sync' OR exs.title = 'Late start')");

        if (!empty($input['search_term'])) {
            $searchTerm = trim($input['search_term']);

            if (!empty($searchTerm)) {
                $searchTerm = preg_replace('/[\t\n\r\0\x0B]/', '', $searchTerm);
                $searchTerm = preg_replace('/([\s])\1+/', ' ', $searchTerm);
                $searchRegex = implode('|', explode(' ', $searchTerm));
                $searchParams = ["%$searchTerm%"];

                $countModel = clone $model;
                $countModel
                    ->where(function ($query) use ($searchParams, $searchRegex) {
                        /** @var Builder $query */
                        $query
                            ->whereRaw('lpwo_person.custom_1 LIKE ?', $searchParams)
                            ->orwhereRaw('lpwo_person.custom_3 LIKE ?', $searchParams)
                            //->orWhereRaw('pd.data_value REGEXP ?', [$searchRegex])
                            ->orWhereRaw('wo_person.custom_1 LIKE ?', $searchParams)
//                            ->orWhereRaw(
//                                "CONCAT_WS(' ', a.address_1, a.city, UPPER(a.state), a.zip_code) REGEXP ?",
//                                [$searchRegex]
//                            );
                            ->orWhereRaw('wo.fin_loc LIKE ?', $searchParams);
                        //->orWhereRaw('t1.type_value LIKE ?', $searchParams);
                    });
                $this->setCountModel($countModel);

                $model
                    ->where(function ($query) use ($searchParams, $searchRegex) {
                        /** @var Builder $query */
                        $query
                            ->whereRaw('lpwo_person.custom_1 LIKE ?', $searchParams)
                            ->orwhereRaw('lpwo_person.custom_3 LIKE ?', $searchParams)
                            //->orWhereRaw('pd.data_value REGEXP ?', [$searchRegex])
                            ->orWhereRaw('wo_person.custom_1 LIKE ?', $searchParams)
//                            ->orWhereRaw(
//                                "CONCAT_WS(' ', a.address_1, a.city, UPPER(a.state), a.zip_code) REGEXP ?",
//                                [$searchRegex]
//                            );
                            ->orWhereRaw('wo.fin_loc LIKE ?', $searchParams);
                        //->orWhereRaw('t1.type_value LIKE ?', $searchParams);
                    });
            }
        }
        // search for teams
        if (!empty($input['data_value'])) {
            $dataValue = trim($input['data_value']);
            if (!empty($dataValue)) {
                $model = $model
                    ->WhereRaw('pd.data_value like ?', $dataValue);
            }
        }
        //search for work_order_number
        if (!empty($input['work_order_number'])) {
            $workOrderNumber = trim($input['work_order_number']);
            if (!empty($workOrderNumber)) {
                $model = $model
                    ->whereRaw('wo.work_order_number LIKE ?', $workOrderNumber);
            }
        }

        if (Arr::has($input, 'resolution_type_id')) {
            $types = explode(',', (string)$input['resolution_type_id']);

            if (in_array(0, $types) || in_array('0', $types)) {
                $model = $model->where(function ($query) use ($types) {
                    /** @var Builder $query */
                    $query
                        ->whereNull('exs.resolution_type_id')
                        ->orWhereIn('exs.resolution_type_id', $types);
                });
            } else {
                $model = $model->whereIn('exs.resolution_type_id', $types);
            }

            unset($input['resolution_type_id']);
            $this->request->replace($input);
        }

        if (Arr::has($input, 'date')) {
            if ($input['date'] != '') {
                if (strlen($input['date']) == 10) {
                    $model = $model->where('exs.date', '=', $input['date']) ;
                };
                if (strlen($input['date']) == 11) {
                    if (stripos($input['date'], ',') == 10) {
                        $input['date'] = trim($input['date'], ',');
                        $model = $model->where('exs.date', '>=', $input['date']) ;
                    }
                    if (stripos($input['date'], ',') === 0) {
                        $input['date'] = trim($input['date'], ',');
                        $model = $model->where('exs.date', '<=', $input['date']) ;
                    }
                }
                if (strlen($input['date']) == 21) {
                    $input['date'] = explode(',', $input['date']);
                    $model = $model->whereBetween('exs.date', $input['date']);
                }
            }
            unset($input['date']);
            $this->request->replace($input);
        }
        //dd($model->toSql());
//        exit;

        $this->setRawColumns(true);
        $this->setWorkingModel($model);

        $data = parent::paginate(50, $columns, [
            'exs.created_at'   => 'DESC',
            'exs.exception_id' => 'DESC',
        ]);

        $this->clearWorkingModel();

        $data->each(function (&$item) {
            if (is_numeric(substr($item->route, strlen($item->route) - 2, 2)) && $item->title != 'Out of sync') {
                $item->route = substr($item->route, 0, strlen($item->route) - 2);
            }
        });
        return $data;
    }
}
