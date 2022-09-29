<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache Pool Information
 */
interface PoolInformationInterface
{
    /**
     * @return CacheItemPoolInterface
     */
    public function getPool(): CacheItemPoolInterface;

    /**
     * Determines if the cache supports delayed persistance of cache items.
     *
     * @return bool
     */
    public function isSaveDeferrable(): bool;
}
