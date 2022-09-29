<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Base Cache Pool Information Aggregator
 */
abstract class AbstractPoolInformation extends AbstractCacheInformation implements
    PoolInformationInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    protected $pool;

    /**
     * Asserts that the cache item pool name is valid, throws an Exception if not.
     *
     * @param  string $poolName The cache item pool name to test.
     * @throws InvalidArgumentException If the pool name is invalid.
     * @return void
     */
    public function assertValidItemPool(string $poolName): void
    {
        if (!$this->isValidItemPool($poolName)) {
            throw new InvalidArgumentException(
                sprintf('Invalid "%s" cache item pool name.', $poolName)
            );
        }
    }

    /**
     * Filters the cache item key.
     *
     * @param  string $key A cache item key.
     * @return string
     */
    public function filterItemKey(string $key): ?string
    {
        $key = filter_var($key, FILTER_SANITIZE_STRING);
        if ($key && is_string($key)) {
            return $key;
        }

        return null;
    }

    /**
     * Filters the cache item keys.
     *
     * @param  string[] $keys Zero or more cache item keys.
     * @return string[]
     */
    public function filterItemKeys(array $keys): array
    {
        $search = [];
        foreach ($keys as $key) {
            $key = $this->filterItemKey($key);
            if ($key !== null) {
                $search[] = $key;
            }
        }

        return $search;
    }

    /**
     * Filters the cache item pool name.
     *
     * @param  string $poolName A cache item pool name.
     * @return string
     */
    public function filterItemPool(string $poolName): ?string
    {
        $poolName = filter_var($poolName, FILTER_SANITIZE_STRING);
        if ($poolName && is_string($poolName)) {
            return $poolName;
        }

        return null;
    }

    /**
     * @return CacheItemPoolInterface
     */
    public function getPool(): CacheItemPoolInterface
    {
        return $this->pool;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslatableName(): string
    {
        return 'cache.pool.'.$this->getName().'.label';
    }

    /**
     * Determines if the cache item pool name is valid.
     *
     * @param  string $poolName The cache item pool name to test.
     * @return bool
     */
    public function isValidItemPool(string $poolName): bool
    {
        return ctype_alnum($poolName);
    }

    /**
     * {@inheritdoc}
     */
    public function isSaveDeferrable(): bool
    {
        return false;
    }
}
