<?php

namespace App\Modules\Address\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Address\Http\Requests\StateRequest;
use App\Modules\Address\Models\State;
use Illuminate\Container\Container;

/**
 * State repository class
 */
class StateRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = ['code', 'name', 'country_id'];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param State     $state
     */
    public function __construct(Container $app, State $state)
    {
        parent::__construct($app, $state);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new StateRequest();

        return $req->getFrontendRules();
    }

    /**
     * Return list of states for each country
     *
     * @param string|array $countryCodes Countries code(s) to choose only
     *     states from
     *
     * @return mixed
     */
    public function getList($countryCodes = [])
    {
        $out = [];

        $model = $this->model->join(
            'countries',
            'countries.id',
            '=',
            'states.country_id'
        )->select([
            'states.code',
            'states.name',
            'countries.code AS c_code',
            ])->orderby('countries.orderby');

        if ($countryCodes) {
            if (!is_array($countryCodes)) {
                $countryCodes = [$countryCodes];
            }
            $model = $model->whereIn('countries.code', $countryCodes);
        }

        $data = $model->get();

        foreach ($data as $item) {
            $out[$item->c_code][$item->code] = $item->name;
        }

        return $out;
    }
}
