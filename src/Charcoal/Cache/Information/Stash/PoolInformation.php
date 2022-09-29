<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Charcoal\Cache\Information\AbstractPoolInformation;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Stash Cache Pool Information Aggregator
 *
 * Stash supports a variety of data store adapters, called drivers,
 * but Stash itself does not support deferred cache items.
 *
 * Notes:
 *
 * - The **item search query** is either a single key name
 *   or an array of key names.
 *   If NULL or omitted, the entire cache pool is queried.
 */
class PoolInformation extends AbstractPoolInformation
{
    /**
     * The {@see \Stash\Pool::getItem() default namespace} used by Stash.
     *
     * @var string
     */
    public const DEFAULT_POOL_NAMESPACE = 'stash_default';

    /**
     * The {@see \Stash\Pool::clear() item data namespace} used by Stash.
     *
     * @var string
     */
    public const DEFAULT_ITEM_DATA_NAMESPACE = 'cache';

    /**
     * The {@see \Stash\Pool::clear() item lock namespace} used by Stash.
     *
     * @var string
     */
    public const DEFAULT_ITEM_LOCK_NAMESPACE = 'sp';

    /**
     * @var DriverInformationInterface
     */
    protected $driverInformation;

    /**
     * @var DriverInformationFactory
     */
    protected $driverInformationFactory;

    /**
     * Store of stampede flags.
     *
     * @var array<string, bool>
     */
    protected $stampedeFlags = [];

    /**
     * @param CacheItemPoolInterface   $pool
     * @param DriverInformationFactory $driverInformationFactory
     */
    public function __construct(
        CacheItemPoolInterface $pool,
        DriverInformationFactory $driverInformationFactory = null
    ) {
        $this->pool = $pool;
        $this->driverInformationFactory = $driverInformationFactory;
    }

    /**
     * Retrieves the pool namespace.
     *
     * @return ?string
     */
    public function getPoolNamespace(): ?string
    {
        return $this->getPool()->getNamespace() ?: self::DEFAULT_POOL_NAMESPACE;
    }

    /**
     * @return DriverInformationInterface
     */
    public function getCacheDriverInformation(): DriverInformationInterface
    {
        if ($this->driverInformation === null) {
            $this->driverInformation = $this->getDriverInformationFactory()->create(
                $this->getPool()->getDriver(),
                $this->getPoolNamespace()
            );
        }

        return $this->driverInformation;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheItems($search = null): iterable
    {
        $items = $this->getCacheDriverInformation()->getCacheItems(
            $this->resolveSearchItemQuery($search)
        );

        foreach ($items as $item) {
            if (!$this->filterCacheItem($item)) {
                continue;
            }

            yield $this->formatCacheItem($item);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheSummary($search = null): array
    {
        return $this->getCacheDriverInformation()->getCacheSummary(
            $this->resolveSearchItemQuery($search)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->getPoolNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function isCacheAvailable(): bool
    {
        return $this->getCacheDriverInformation()->isCacheAvailable();
    }

    /**
     * {@inheritdoc}
     */
    public function isSavePersistent(): bool
    {
        return $this->getCacheDriverInformation()->isSavePersistent();
    }

    /**
     * Resolves any search item query.
     *
     * @param  ?(string|string[]) $search The cache item search query.
     * @return ?(string|string[])
     */
    public function resolveSearchItemQuery($search = null)
    {
        if (is_array($search) && !$search) {
            return null;
        }

        return $search;
    }

    /**
     * Filters the cache item information.
     *
     * @param  array<string, mixed> $item The cache item.
     * @return bool
     */
    protected function filterCacheItem(array $item): bool
    {
        $key = $item['key'];

        // Exclude stampede flags, marking the related item
        if ($item['type'] === self::DEFAULT_ITEM_LOCK_NAMESPACE) {
            $this->stampedeFlags[$key] = true;
            return false;
        } elseif (!isset($this->stampedeFlags[$key])) {
            $this->stampedeFlags[$key] = false;
        }

        return true;
    }

    /**
     * Formats the cache item information.
     *
     * @param  array<string, mixed> $item The cache item.
     * @return array<string, mixed>
     */
    protected function formatCacheItem(array $item): array
    {
        $key = $item['key'];

        $item['locked'] = ($this->stampedeFlags[$key] ?? false);

        return $item;
    }

    /**
     * Retrieves the driver information factory.
     *
     * @return DriverInformationFactory
     */
    protected function getDriverInformationFactory(): DriverInformationFactory
    {
        if ($this->driverInformationFactory === null) {
            $this->driverInformationFactory = new DriverInformationFactory();
        }

        return $this->driverInformationFactory;
    }
}
