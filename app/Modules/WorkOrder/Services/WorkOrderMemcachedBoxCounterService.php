<?php

namespace App\Modules\WorkOrder\Services;

use App\Core\MemcachedTrait;
use Illuminate\Contracts\Container\Container;

class WorkOrderMemcachedBoxCounterService extends WorkOrderBoxCounterService implements WorkOrderBoxCounterServiceContract
{
    use MemcachedTrait;

    protected $tagName = 'workorder_counter';

    /**
     * {@inheritdoc}
     */
    public function __construct(
        WorkOrderItemsCounterService $counter,
        Container $app
    ) {
        parent::__construct($counter, $app);
        $this->setValidTagName();
    }

    /**
     * {@inheritdoc}
     */
    public function generate()
    {
        $cacheId = 'workorderCountergenerate';

        return $this->app['cache']->tags($this->tagName)
            ->remember(
                $cacheId,
                5,
                function () {
                    return parent::generate();
                }
            );
    }
}
