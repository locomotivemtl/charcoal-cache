<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use InvalidArgumentException;
use ReflectionClass;
use Stash\DriverList;
use Stash\Interfaces\DriverInterface;

/**
 * Cache Driver Information Factory
 */
class DriverInformationFactory
{
    /**
     * Creates an information aggregator for the given Stash driver.
     *
     * @param  DriverInterface $driver
     * @param  ?string         $poolNamespace
     * @return DriverInformationInterface
     */
    public function create(DriverInterface $driver, ?string $poolNamespace): DriverInformationInterface
    {
        $driverInfoClass = ($this->resolveDriverInformationClass($driver) ?? $this->getFallbackDriverInformationClass());

        return $this->buildDriverInformation($driverInfoClass, $driver, $poolNamespace);
    }

    /**
     * Creates an information aggregator for the given Stash driver,
     * or throws an Exception if unsupported.
     *
     * @param  DriverInterface $driver
     * @param  ?string         $poolNamespace
     * @throws InvalidArgumentException If the driver is unsupported.
     * @return DriverInformationInterface
     */
    public function createOrFail(DriverInterface $driver, ?string $poolNamespace): DriverInformationInterface
    {
        $driverInfoClass = $this->resolveDriverInformationClass($driver);
        if ($driverInfoClass) {
            return $this->buildDriverInformation($driverInfoClass, $driver, $poolNamespace);
        }

        throw new InvalidArgumentException(
            'Unsupported driver: '.get_class($driver)
        );
    }

    /**
     * Creates the given information aggregator with Stash driver.
     *
     * @param  string          $driverInfoClass
     * @param  DriverInterface $driver
     * @param  ?string         $poolNamespace
     * @return DriverInformationInterface
     */
    protected function buildDriverInformation(string $driverInfoClass, DriverInterface $driver, ?string $poolNamespace): DriverInformationInterface
    {
        if (strpos($driverInfoClass, 'Composite') !== false) {
            return $driverInfoClass::create($driver, $poolNamespace, $this);
        }

        return $driverInfoClass::create($driver, $poolNamespace);
    }

    /**
     * Retrieves the fallback information aggregator class.
     *
     * @return string
     */
    protected function getFallbackDriverInformationClass(): string
    {
        return GenericDriverInformation::class;
    }

    /**
     * Resolves the information aggregator class for the given Stash driver.
     *
     * @param  DriverInterface $driver
     * @return ?string
     */
    protected function resolveDriverInformationClass(DriverInterface $driver): ?string
    {
        $registeredDrivers = array_flip(DriverList::getAllDrivers());

        $driverClass = '\\'.get_class($driver);
        if (isset($registeredDrivers[$driverClass])) {
            $driverName = $registeredDrivers[$driverClass];
        } else {
            $reflection = new ReflectionClass($driver);
            $driverName = $reflection->getShortName();
        }

        $driverInfoClass = __NAMESPACE__.'\\'.$driverName.'DriverInformation';
        if (class_exists($driverInfoClass)) {
            return $driverInfoClass;
        }

        return null;
    }
}
