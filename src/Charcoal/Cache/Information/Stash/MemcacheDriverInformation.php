<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Closure;
use InvalidArgumentException;
use Memcache;
use Memcached;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stash\Driver\Memcache as MemcacheDriver;
use Stash\Driver\Sub\Memcache as SubMemcacheDriver;
use Stash\Driver\Sub\Memcached as SubMemcachedDriver;

/**
 * Stash Memcache Cache Driver Information Aggregator
 *
 * Note: This class has NOT been tested.
 */
class MemcacheDriverInformation extends AbstractDriverInformation
{
    /**
     * A closure for the {@see \Stash\Driver\Memcache::makeKeyString() key maker method}
     * from the Stash driver.
     *
     * @var Closure
     */
    protected $keyMaker;

    /**
     * The {@see \Stash\Driver\Sub\Memcache::$memcache Memcache}
     * or {@see \Stash\Driver\Sub\Memcached::$memcached Memcached}
     * client used by the Memcache adapter.
     *
     * @var Memcache|Memcached
     */
    protected $client;

    /**
     * The {@see \Stash\Driver\Memcache::$memcache Memcache or Memcached adapter}
     * used by the Stash driver.
     *
     * @var SubMemcacheDriver|SubMemcachedDriver
     */
    protected $subDriver;

    /**
     * {@inheritdoc}
     */
    public function getCacheSummary($search = null): array
    {
        $summary = $this->getBaseCacheSummary($search);

        $stats = $this->getClient()->getStats();

        if (!is_array($stats) || !$stats) {
            return $summary;
        }

        $stats = reset($stats);

        $summary['totalCount']  = @$stats['curr_items'];
        $summary['totalHits']   = @$stats['get_hits'];
        $summary['totalMisses'] = @$stats['get_misses'];
        $summary['totalSize']   = @$stats['bytes'];

        return $summary;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return get_class($this->getClient());
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslatableName(): string
    {
        return 'cache.stash.driver.'.$this->getName().'.label';
    }

    /**
     * {@inheritdoc}
     */
    public function isDriverSupported(): bool
    {
        return true;
    }

    /**
     * @param MemcacheDriver $driver
     * @param ?string        $poolNamespace
     */
    protected function __construct(
        MemcacheDriver $driver,
        ?string $poolNamespace
    ) {
        $this->driver = $driver;
        $this->poolNamespace = $poolNamespace;
    }

    /**
     * @throws RuntimeException If a Memcache extension can not be extracted.
     * @return Memcache|Memcached
     */
    protected function extractClient()
    {
        $subDriver = $this->getSubDriver();

        if ($subDriver instanceof SubMemcacheDriver) {
            $property = 'memcache';
        } elseif ($subDriver instanceof SubMemcachedDriver) {
            $property = 'memcached';
        } else {
            throw new RuntimeException(
                'Unable to extract Memcache/Memcached extension from driver'
            );
        }

        $reflection = new ReflectionProperty($subDriver, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($subDriver);
    }

    /**
     * @return Closure
     */
    protected function extractKeyMaker(): Closure
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionMethod($driver, 'makeKeyString');
        $reflection->setAccessible(true);

        return $reflection->getClosure($driver);
    }

    /**
     * @throws RuntimeException If a sub-driver can not be extracted.
     * @return SubMemcacheDriver|SubMemcachedDriver
     */
    protected function extractSubDriver()
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionProperty($driver, 'memcache');
        $reflection->setAccessible(true);

        $subDriver = $reflection->getValue($driver);

        if (
            ($subDriver instanceof SubMemcacheDriver) ||
            ($subDriver instanceof SubMemcachedDriver)
        ) {
            return $subDriver;
        }

        throw new RuntimeException(
            'Unable to extract Memcache/Memcached adapter from driver'
        );
    }

    /**
     * @return Memcache|Memcached
     */
    protected function getClient()
    {
        if ($this->client === null) {
            $this->client = $this->extractClient();
        }

        return $this->client;
    }

    /**
     * @return Closure
     */
    protected function getKeyMaker(): Closure
    {
        if ($this->keyMaker === null) {
            $this->keyMaker = $this->extractKeyMaker();
        }

        return $this->keyMaker;
    }

    /**
     * @return SubMemcacheDriver|SubMemcachedDriver
     */
    protected function getSubDriver()
    {
        if ($this->subDriver === null) {
            $this->subDriver = $this->extractSubDriver();
        }

        return $this->subDriver;
    }

    /**
     * Alias of {@see \Stash\Driver\Memcache::makeKeyString()}
     *
     * @param  array $key
     * @param  bool  $path
     * @return string
     */
    protected function makeKey(array $key, $path = false): string
    {
        $makeKey = $this->getKeyMaker();

        return $makeKey($key, $path);
    }
}
