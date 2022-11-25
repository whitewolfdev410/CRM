<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\MemcachedTrait;
use App\Modules\Type\Repositories\TypeRepository;
use App\Modules\WorkOrder\Repositories\WorkOrderRepository;
use Illuminate\Contracts\Container\Container;

class WorkOrderMemcachedDataService extends WorkOrderDataService implements
    WorkOrderDataServiceContract
{
    use MemcachedTrait;

    /**
     * Tag name for caching
     *
     * @var string
     */
    protected $tagName = 'workorder_data';

    /**
     * {@inheritdoc}
     */
    public function __construct(
        TypeRepository $typeRepository,
        WorkOrderRepository $woRepository,
        Container $app
    ) {
        parent::__construct($typeRepository, $woRepository, $app);
        $this->setValidTagName();
    }

    /**
     * {@inheritdoc}
     */
    public function getAll()
    {
        $cacheId = 'workorderDatagetAll';

        return $this->app['cache']->tags($this->tagName)
            ->remember(
                $cacheId,
                5,
                function () {
                    return parent::getAll();
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        $cacheId = 'workorderDatagetValues';

        return $this->app['cache']->tags($this->tagName)
            ->remember(
                $cacheId,
                5,
                function () {
                    return parent::getValues();
                }
            );
    }
}
