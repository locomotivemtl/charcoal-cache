<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information;

use Charcoal\Cache\Information\Stash\PoolInformation;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache Pool Information Factory
 */
class PoolInformationFactory
{
    /**
     * @param  CacheItemPoolInterface $pool
     * @param  mixed[]                ...$args
     * @return CacheInformationInterface
     */
    public static function create(CacheItemPoolInterface $pool, ...$args): CacheInformationInterface {
        return new PoolInformation($pool, ...$args);
    }
}
