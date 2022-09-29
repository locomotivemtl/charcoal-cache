<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information;

/**
 * Base Cache Information Aggregator
 */
abstract class AbstractCacheInformation implements CacheInformationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getCacheItems($search = null): iterable
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheSummary($search = null): array
    {
        return $this->getBaseCacheSummary();
    }

    /**
     * {@inheritdoc}
     */
    public function isCacheAvailable(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isSavePersistent(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBaseCacheItem(): array
    {
        return [
            'type'           => null,
            'locked'         => null,
            'value'          => null,
            'key'            => null,
            'formattedKey'   => null,
            'creationTime'   => null,
            'modifiedTime'   => null,
            'deletionTime'   => null,
            'accessTime'     => null,
            'expirationTime' => null,
            'ttl'            => null,
            'hits'           => null,
            'misses'         => null,
            'size'           => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBaseCacheSummary(): array
    {
        return [
            'name'             => $this->getName(),
            'translatableName' => $this->getTranslatableName(),
            'isAvailable'      => $this->isCacheAvailable(),
            'isPersistent'     => $this->isSavePersistent(),
            'totalCount'       => null,
            'totalHits'        => null,
            'totalMisses'      => null,
            'totalSize'        => null,
        ];
    }
}
