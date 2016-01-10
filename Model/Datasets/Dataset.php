<?php

/*
 * This file is part of the Spira framework.
 *
 * @link https://github.com/spira/spira
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Spira\Core\Model\Datasets;

use Illuminate\Support\Facades\Cache;
use ReflectionClass;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

abstract class Dataset
{
    /**
     * Cache repository.
     *
     * @var CacheRepository
     */
    protected $cache;

    /**
     * Assign dependencies.
     *
     */
    public function __construct()
    {
        $this->cache = Cache::store();
    }

    /**
     * Get the dataset collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all()
    {
        $cacheKey = 'dataset'.(new ReflectionClass($this))->getShortName();

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $dataset = $this->cache->rememberForever($cacheKey, function () {
            return $this->getDataset();
        });

        return $dataset;
    }

    /**
     * Get the dataset.
     *
     * @return \Illuminate\Support\Collection
     */
    abstract protected function getDataset();
}
