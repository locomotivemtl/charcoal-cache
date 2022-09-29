<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information;

/**
 * Cache Information
 */
interface CacheInformationInterface
{
    /**
     * Retrieves the cache items.
     *
     * @param  ?(string|string[]) $search The cache key(s) to lookup.
     *     If NULL or omitted, all data is looked-up.
     * @return iterable
     */
    public function getCacheItems($search = null): iterable;

    /**
     * Retrieves a summary of information and statistics from the data store.
     *
     * @param  ?(string|string[]) $search The cache key(s) to lookup.
     *     If NULL or omitted, all data is looked-up.
     * @return array<string, mixed>
     */
    public function getCacheSummary($search = null): array;

    /**
     * Retrieves the name of the cache or aggregator.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Retrieves the translatable name of the cache or aggregator.
     *
     * @return string
     */
    public function getTranslatableName(): string;

    /**
     * Determines if the data store is available.
     *
     * @return bool
     */
    public function isCacheAvailable(): bool;

    /**
     * Determines if the data store is persistent.
     *
     * @return bool
     */
    public function isSavePersistent(): bool;
}
