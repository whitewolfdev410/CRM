<?php

namespace App\Modules\Address\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Address\Models\Currency;
use Illuminate\Container\Container;
use App\Modules\Address\Http\Requests\CurrencyRequest;

/**
 * Currency repository class
 */
class CurrencyRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = ['code', 'name'];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Currency $currency
     */
    public function __construct(Container $app, Currency $currency)
    {
        parent::__construct($app, $currency);
    }

    /**
     * Get front-end validation rules
     *
     * @return array
     */
    public function getRequestRules()
    {
        $req = new CurrencyRequest();

        return $req->getFrontendRules();
    }
}
