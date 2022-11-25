<?php

namespace App\Core;

trait MemcachedRepositoryTrait
{
    use MemcachedTrait;

    /**
     * {@inheritdoc}
     */
    public function create(array $input)
    {
        $return = parent::create($input);
        $this->clearCache();

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function updateWithIdAndInput($id, array $input)
    {
        $return = parent::updateWithIdAndInput($id, $input);
        $this->clearCache();

        return $return;
    }

    /**
     * Clear cache
     */
    public function clearCache()
    {
        $this->app['cache']->tags($this->tagName)->flush();
    }
}
