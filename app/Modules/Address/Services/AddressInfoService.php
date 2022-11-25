<?php

namespace App\Modules\Address\Services;

use App\Modules\Address\Models\AddressInfo;
use App\Modules\AddressIssue\Repositories\AddressIssueRepository;
use Illuminate\Container\Container;

class AddressInfoService
{
    /**
     * @var Container
     */
    protected $app;
    /**
     * @var AddressInfoRepository
     */
    protected $address_info_repository;

    /**
     * Initialize class parameters
     *
     * @param Container              $app
     * @param AddressInfoRepository $address_info_repository
     */
    public function __construct(Container $app, AddressInfoRepository $address_info_repository)
    {
        $this->app = $app;
        $this->address_info_repository = $address_info_repository;
    }

    /**
     * Add new info
     *
     * @param array $input
     *
     * @return AddressInfo
     */
    public function add($input)
    {
        return $this->address_info_repository->create($input);
    }
}
