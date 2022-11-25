<?php

namespace App\Modules\Type\Repositories;

use App\Core\MemcachedRepositoryTrait;
use Illuminate\Container\Container;
use App\Modules\Type\Models\Type;

/**
 * Class TypeMemcachedRepository
 *
 * Decorator for TypeRepository - it caches results in Memcached
 *
 * @package App\Modules\Type\Repositories
 */
class TypeMemcachedRepository extends TypeRepository
{
    use MemcachedRepositoryTrait;

    /**
     * Tag name for caching
     *
     * @var string
     */
    protected $tagName = 'types';

    /**
     * Initialize object (run parent constructor) and sets valid tagName group
     * (prepend environment name)
     *
     * @param Container $app
     * @param Type $type
     */
    public function __construct(Container $app, Type $type)
    {
        parent::__construct($app, $type);

        $this->setValidTagName();
    }

    /**
     * {@inheritdoc}
     */
    public function getColumnByKey(
        $key,
        $withChildren = false,
        $column = 'type_id'
    ) {
        $cacheId = 'typegetColumnByKey' . $this->getHashedKey($key
                . ((int)$withChildren) . $column);

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($key, $withChildren, $column) {
                    return parent::getColumnByKey($key, $withChildren, $column);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getListByKeys(
        array $keys = [],
        $value = 'type_id',
        $key = 'type_key'
    ) {
        $cacheId = 'typegetListByKeys' . $this->getHashedKey(implode('', $keys)
                . $value . $key);

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($keys, $value, $key, $cacheId) {
                    return parent::getListByKeys($keys, $value, $key);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getList($types, $value = 'type_value', $key = 'type_id')
    {
        $cacheId
            =
            'typegetList' . $this->getHashedKey((is_array($types) ? implode(
                '',
                $types
            ) : $types) . $value . $key);

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($types, $value, $key) {
                    return parent::getList($types, $value, $key);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getListByIds(array $ids)
    {
        $cacheId = 'typegetListByIds' . implode('', $ids);

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($ids) {
                    return parent::getListByIds($ids);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getMultipleLists(array $types)
    {
        $cacheId
            = 'typegetMultipleLists' . $this->getHashedKey(implode('', $types));

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($types) {
                    return parent::getMultipleLists($types);
                }
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getValueById($id)
    {
        $cacheId = 'typegetValueById' . ((int)$id);

        return $this->app['cache']->tags($this->tagName)
            ->rememberForever(
                $cacheId,
                function () use ($id) {
                    return parent::getValueById($id);
                }
            );
    }
}
