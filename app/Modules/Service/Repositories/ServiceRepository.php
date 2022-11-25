<?php

namespace App\Modules\Service\Repositories;

use Illuminate\Support\Facades\App;
use App\Core\AbstractRepository;
use App\Core\Exceptions\InvalidTypeKeyException;
use App\Modules\Person\Models\Person;
use App\Modules\PricingStructure\Repositories\PricingMatrixRepository;
use App\Modules\PricingStructure\Repositories\PricingStructureRepository;
use App\Modules\Service\Http\Requests\ServiceRequest;
use App\Modules\Service\Models\Service;
use App\Modules\Type\Repositories\TypeRepository;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service repository class
 */
class ServiceRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [
            'service_name',
            'enabled',
            'short_description',
            'long_description',
            'unit',
            'category_type_id',
            'msrp',
            'service_key',

            'category_type_id_value',
            'service.category_type_id',
        ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Service   $service
     */
    public function __construct(Container $app, Service $service)
    {
        parent::__construct($app, $service);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new ServiceRequest();

        return $req->getFrontendRules();
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     * @throws InvalidTypeKeyException
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['service.*'],
        array $order = []
    ) {
        /** @var Builder|Object|Service $model */
        $model = $this->model;

        // by default get unit and category relationship if no with_relations=0
        $withRelations = $this->request->input('with_relations', 1);
        if ($withRelations) {
            $model = $model->with('unitRel', 'categoryTypeRel');
        }

        $model = $model
            ->leftJoin(
                'type',
                'service.category_type_id',
                '=',
                'type.type_id'
            );
        $columns[] = 'type.type_value as category_type_id_value';

        // filter by category_type_id if category_type_key is present
        $categoryTypeKey = trim($this->request->input('category_type_key', ''));
        if ($categoryTypeKey) {
            $categoryTypeId = getTypeIdByKey($categoryTypeKey);
            if ($categoryTypeId) {
                $model = $model->where('category_type_id', $categoryTypeId);
            } else {
                // invalid type key used - throw exception - it should not happen
                $exception = $this->app->make(InvalidTypeKeyException::class);
                $exception->setData(['type_key' => $categoryTypeKey]);
                throw $exception;
            }
        }

        $this->setWorkingModel($model);

        $data = parent::paginate($perPage, $columns, $order);
        $this->clearWorkingModel();

        // if detailed load also pricing structures and pricing matrix
        $detailed = $this->request->input('detailed', '');
        if ($detailed === 'ps') {
            // need to convert to array if we want to set extra data later
            $data = $data->toArray();
            $servicesIds = [];
            foreach ($data['data'] as $item) {
                $servicesIds[] = $item['id'];
            }
            $pricings = [];
            $matrix = [];

            if ($servicesIds) {
                $matrixRepo = $this->makeRepository(
                    'PricingMatrix',
                    'PricingStructure'
                );

                list($pricings, $matrix)
                    = $matrixRepo->getForServices($servicesIds);
            }

            $data['pricing_structures'] = $pricings;
            $data['matrix'] = $matrix;
        }

        return $data;
    }

    /**
     *  {@inheritdoc}
     */
    public function find(
        $id,
        array $columns = ['*']
    ) {
        /** @var Builder|Object|Service $model */
        $model = $this->model;

        $model = $model
            ->leftJoin(
                'type AS unit_t',
                'unit_t.type_id',
                '=',
                'service.unit'
            )
            ->leftJoin(
                'type AS category_type_t',
                'category_type_t.type_id',
                '=',
                'service.category_type_id'
            );

        $columns = [
            'service.*',
            'unit_t.type_value AS unit_value',
            'category_type_t.type_value AS category_type_id_value',
        ];

        $this->setWorkingModel($model);

        $data = parent::find($id, $columns);
        $this->clearWorkingModel();

        return $data;
    }


    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function create(array $input)
    {
        $created = null;
        $matrix = null;

        DB::transaction(function () use ($input, &$created, &$matrix) {
            $created = parent::create($input);
            $matrix = $this->setPriceForPricingStructures($created->id, $input);
        });

        return [$created, $matrix];
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $object = null;
        $matrix = null;

        DB::transaction(function () use ($id, $input, &$object, &$matrix) {
            $object = parent::updateWithIdAndInput($id, $input);
            $matrix = $this->setPriceForPricingStructures($object->id, $input);
        });

        return [$object, $matrix];
    }

    /**
     * Set prices for given $serviceId in PricingMatrix
     *
     * @param int   $serviceId
     * @param array $input
     *
     * @return mixed
     */
    protected function setPriceForPricingStructures($serviceId, array $input)
    {
        $matrixRepo = App::make(PricingMatrixRepository::class);

        $prices = $this->getValidPrices($input['price']);

        $functions = (isset($input['has_function'])
            && is_array($input['has_function']))
            ? $input['has_function'] : [];

        return $matrixRepo->synchronizeService($serviceId, $prices, $functions);
    }


    /**
     * Get valid array prices from input (choose only existing ids)
     *
     * @param array $prices
     *
     * @return array
     */
    protected function getValidPrices(array $prices)
    {
        $prRepo = $this->getPricingStructureRepository();

        $validIds = $prRepo->getValidIds(array_keys($prices));

        return array_intersect_key($prices, array_flip($validIds));
    }

    /**
     * Get list of valid ids for services (checks existence in DB)
     *
     * @param array $ids
     *
     * @return array
     */
    public function getValidIds(array $ids)
    {
        if (!$ids) {
            return [];
        }

        /** @var Builder|EloquentBuilder|Service $model */
        $model = $this->model;

        return $model
            ->whereIn($this->model->getKeyName(), $ids)
            ->pluck($this->model->getKeyName())
            ->all();
    }


    /**
     * Get PricingStructure joined with PricingMatrix for given $serviceId
     *
     * @param int $serviceId
     *
     * @return mixed
     */
    public function getPricingStructureWithMatrix($serviceId = 0)
    {
        $prRepo = $this->getPricingStructureRepository();

        return $prRepo->getForServiceWithMatrix($serviceId);
    }

    /**
     * Get PricingStructureRepository object
     *
     * @return mixed
     */
    protected function getPricingStructureRepository()
    {
        return App::make(PricingStructureRepository::class);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function show($id, $full = false)
    {
        $output['item'] = $this->find($id);

        if ($full) {
            $output['fields'] = $this->getConfig();
            $output['pricing_structure'] = $this->getPricingStructureWithMatrix($id);
        }

        return $output;
    }


    /**
     * Get module configuration - request rules together with data for lists
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getConfig()
    {
        $output = $this->getRequestRules();

        $tRep = \App::make(TypeRepository::class);

        $output['unit']['data'] = $tRep->getList('unit');
        $output['category_type_id']['data']
            = $tRep->getList('service_category');

        return $output;
    }

    /**
     * Get all services together with pricing_matrixes that have given ids
     *
     * @param array $pricingStructureIds
     *
     * @return mixed
     */
    public function getWithMatrixPrices(array $pricingStructureIds)
    {
        /** @var Builder|EloquentBuilder|Service $model */
        $model = $this->model;

        $model = $model
            ->with([
                'pricingMatrixes' => function ($query) use ($pricingStructureIds) {
                    /** @var Builder $query */
                    $query
                        ->whereIn('pricing_structure_id', $pricingStructureIds)
                        ->select(
                            'pricing_matrix.pricing_matrix_id',
                            'pricing_matrix.pricing_structure_id',
                            'pricing_matrix.service_id',
                            'pricing_matrix.price'
                        );
                },
            ]);

        return $model
            ->with([
                'pricingMatrixes' => function ($query) use ($pricingStructureIds) {
                    /** @var Builder $query */
                    $query
                        ->whereIn('pricing_structure_id', $pricingStructureIds)
                        ->select(
                            'pricing_matrix.pricing_matrix_id',
                            'pricing_matrix.pricing_structure_id',
                            'pricing_matrix.service_id',
                            'pricing_matrix.price'
                        );
                },
            ])
            ->select(
                'service.service_id',
                'service.service_name',
                'service.enabled',
                'service.date_created',
                'service.date_modified'
            )
            ->get();
    }

    /**
     * Find first service with given name
     *
     * @param string $name
     * @param string $operator
     * @param bool   $addLikeSearch
     *
     * @return mixed
     */
    public function findFirstByName(
        $name,
        $operator = '=',
        $addLikeSearch = true
    ) {
        /** @var Builder|EloquentBuilder|Service $model */
        $model = $this->model;

        if ($operator === 'LIKE') {
            if ($addLikeSearch) {
                $name = '%' . $name . '%';
            }
            $model = $model->where('service_name', 'LIKE', $name);
        } else {
            $model = $model->where('service_name', $operator, $name);
        }

        return $model->first();
    }

    /**
     * Get list containing names => ids
     *
     * @return array
     */
    public function getListByName()
    {
        /** @var Builder|EloquentBuilder|Service $model */
        $model = $this->model;

        $list = [];
        $result = $model
            ->select(['service_id', 'service_name'])
            ->get();

        foreach ($result as $item) {
            $list[strtolower(trim($item->service_name))] = $item->service_id;
        }

        // map service name to type name
        if (config('app.crm_user') === 'fs') {
            if (isset($list['on-site - 1.5 hr min']) && !isset($list['on-site support'])) {
                $list['on-site support'] = $list['on-site - 1.5 hr min'];
            }

            if (isset($list['fs|proactive']) && !isset($list['customer service'])) {
                $list['customer service'] = $list['fs|proactive'];
            }
        }
        
        return $list;
    }
    
    /**
     * Get list containing names => ids . If there are is no service for any of
     * given name for this name null will be returned as id
     *
     * @param array $names
     *
     * @return array
     */
    public function getNamesIdsList(array $names)
    {
        /** @var Builder|EloquentBuilder|Service $model */
        $model = $this->model;

        $list = $model
            ->whereIn('service_name', $names)
            ->pluck('service_id', 'service_name')
            ->all();

        foreach ($names as $name) {
            if (!isset($list[$name])) {
                $list[$name] = null;
            }
        }

        return $list;
    }

    /**
     * @param int  $personId
     * @param bool $withDetails
     *
     * @return Service|Collection
     *
     * @throws InvalidArgumentException
     */
    public function getEnabledServices(
        $personId = 0,
        $withDetails = false
    ) {
        /** @var Service $model */
        $model = $this->model;

        /** @var Person $person */
        $person = Person::find($personId);

        if ($withDetails) {
            $model = $model
                ->with([
                    'pricingFunctions' => function ($query) use ($person) {
                        if ($person) {
                            /** @var Builder $query */
                            $query->where('pricing_structure_id', '=', $person->getPricingStructureId());
                        }
                    },
                    'pricingMatrixes'  => function ($query) use ($person) {
                        if ($person) {
                            /** @var Builder $query */
                            $query->where('pricing_structure_id', '=', $person->getPricingStructureId());
                        }
                    },
                    'unitRel',
                ]);
        }

        return $model
            ->where('enabled', '=', 1)
            ->get();
    }

    public function getCities($state)
    {
        return DB::table('service_zones')
            ->select(['city'])
            ->where('state', $state)
            ->groupBy('city')
            ->orderBy('city')
            ->get()
            ->map(function ($value) {
                return [ "label" => $value->city, "value" => $value->city ];
            });
    }
}
