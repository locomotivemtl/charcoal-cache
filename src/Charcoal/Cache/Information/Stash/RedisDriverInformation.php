<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Closure;
use Redis;
use RedisArray;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stash\Driver\Redis as RedisDriver;

/**
 * Stash Redis Cache Driver Information Aggregator
 *
 * Note: This class has NOT been tested.
 */
class RedisDriverInformation extends AbstractDriverInformation
{
    /**
     * A closure for the {@see \Stash\Driver\Redis::makeKeyString() key maker method}
     * from the Stash driver.
     *
     * @var Closure
     */
    protected $keyMaker;

    /**
     * The client instance used by the Redis driver.
     *
     * @var Redis|RedisArray
     */
    protected $client;

    /**
     * {@inheritdoc}
     */
    public function getCacheSummary($search = null): array
    {
        $summary = $this->getBaseCacheSummary($search);

        $stats  = $this->getClient()->info('STATS');
        $memory = $this->getClient()->info('MEMORY');

        $summary['totalCount']  = @$stats['tracking_total_keys'];
        $summary['totalHits']   = @$stats['keyspace_hits'];
        $summary['totalMisses'] = @$stats['keyspace_misses'];
        $summary['totalSize']   = @$memory['used_memory_dataset'];

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
    public function isDriverSupported(): bool
    {
        return true;
    }

    /**
     * @param RedisDriver $driver
     * @param ?string     $poolNamespace
     */
    protected function __construct(
        RedisDriver $driver,
        ?string $poolNamespace
    ) {
        $this->driver = $driver;
        $this->poolNamespace = $poolNamespace;
    }

    /**
     * @throws RuntimeException If a Redis extension can not be extracted.
     * @return Redis|RedisArray
     */
    protected function extractClient()
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionProperty($driver, 'redis');
        $reflection->setAccessible(true);

        return $reflection->getValue($driver);
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
     * @return Redis|RedisArray
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
     * Alias of {@see \Stash\Driver\Redis::makeKeyString()}
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
