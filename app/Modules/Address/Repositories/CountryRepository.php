<?php

namespace App\Modules\Address\Repositories;

use Illuminate\Support\Str;
use App\Core\AbstractRepository;
use App\Modules\Address\Http\Requests\CountryRequest;
use App\Modules\Address\Models\Country;
use Illuminate\Container\Container;

/**
 * Country repository class
 */
class CountryRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable
        = [
            'code',
            'name',
            'orderby',
            'phone_prefix',
            'currency',
        ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Country $country
     */
    public function __construct(Container $app, Country $country)
    {
        parent::__construct($app, $country);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = ['orderby', 'id']
    ) {
        return parent::paginate($perPage, $columns, $order);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new CountryRequest();

        return $req->getFrontendRules();
    }


    /**
     * {@inheritdoc}
     */
    public function create(array $input)
    {
        $input['currency'] = $this->setCurrency($input);
        $input['phone_prefix'] = $this->setPhonePrefix($input);

        return parent::create($input);
    }

    /**
     * {@inheritdoc}
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $input['currency'] = $this->setCurrency($input);
        $input['phone_prefix'] = $this->setPhonePrefix($input);

        return parent::updateWithIdAndInput($id, $input);
    }

    /**
     * Set currency to null if null or empty
     *
     * @param array $input
     *
     * @return null|string
     */
    protected function setCurrency(array $input)
    {
        if (!$input['currency']) {
            return null;
        }

        return $input['currency'];
    }

    /**
     * Removes + sign from the beginning of phone prefix
     *
     * @param array $input
     *
     * @return string
     */
    public function setPhonePrefix(array $input)
    {
        if (Str::startsWith($input['phone_prefix'], '+')) {
            return trim(ltrim($input['phone_prefix'], '+'));
        }

        return $input['phone_prefix'];
    }


    /**
     * Return list of items
     *
     * @param string|array $codes Countries code(s) to choose only
     *
     * @return mixed
     */
    public function getList($codes = [])
    {
        $model = $this->model->orderBy('orderby');
        if ($codes) {
            if (!is_array($codes)) {
                $codes = [$codes];
            }
            $model = $model->whereIn('code', $codes);
        }

        $this->setWorkingModel($model);
        $data = parent::pluck('name', 'code');
        $this->clearWorkingModel();

        return $data;
    }
}
