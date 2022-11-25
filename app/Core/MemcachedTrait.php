<?php

namespace App\Core;

use Illuminate\Support\Facades\App;
use App\Core\Exceptions\MemcachedAppNotSetException;
use App\Core\Exceptions\MemcachedTagNameNotSetException;

/**
 * Trait MemcachedTrait
 *
 * Trait to help implementing memcached repositories
 *
 * @package App\Core
 */
trait MemcachedTrait
{
    /**
     *  Set correct tagName (append environment name)
     */
    protected function setValidTagName()
    {
        if (empty($this->tagName)) {
            throw App::make(MemcachedTagNameNotSetException::class);
        }
        if (!isset($this->app)) {
            throw App::make(MemcachedAppNotSetException::class);
        }
        $this->tagName = $this->app->environment() . '_' . $this->tagName;
    }

    /**
     * Returned hashed version of $key
     *
     * @param string $key
     *
     * @return string
     */
    protected function getHashedKey($key)
    {
        return hash('sha256', $key);
    }
}
