<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Charcoal\Cache\Information\AbstractCacheInformation;
use ReflectionClass;
use Stash\DriverList;
use Stash\Interfaces\DriverInterface;

/**
 * Base Cache Driver Information Aggregator
 */
abstract class AbstractDriverInformation extends AbstractCacheInformation implements
    DriverInformationInterface
{
    /**
     * The Stash Pool {@link http://www.stashphp.com/Grouping.html#namespaces namespace}.
     *
     * This allows different sections to be cleared on an individual level,
     *
     * @var string
     */
    protected $poolNamespace;

    /**
     * The main Stash driver.
     *
     * @var DriverInterface
     */
    protected $driver;

    /**
     * @param  DriverInterface $driver
     * @param  mixed[]         ...$args
     * @return DriverInformationInterface
     */
    public static function create(DriverInterface $driver, ...$args): DriverInformationInterface {
        return new static($driver, ...$args);
    }

    /**
     * @return DriverInterface
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->resolveDriverName();
    }

    /**
     * Retrieves the pool namespace.
     *
     * @return ?string
     */
    public function getPoolNamespace(): ?string
    {
        return $this->poolNamespace;
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
    public function isDriverAggregator(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCacheAvailable(): bool
    {
        return $this->isDriverSupported();
    }

    /**
     * {@inheritdoc}
     */
    public function isSavePersistent(): bool
    {
        return $this->getDriver()->isPersistent();
    }

    /**
     * Resolves the Stash driver short name or alias.
     *
     * @return string
     */
    protected function resolveDriverName(): string
    {
        $registeredDrivers = array_flip(DriverList::getAllDrivers());

        $driverClass = '\\'.get_class($this->getDriver());
        if (isset($registeredDrivers[$driverClass])) {
            return $registeredDrivers[$driverClass];
        }

        $reflection = new ReflectionClass($driver);
        return $reflection->getShortName();
    }
}
