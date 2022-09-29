<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use ReflectionProperty;
use Stash\Driver\Composite as CompositeDriver;
use Stash\Interfaces\DriverInterface;

/**
 * Stash Composite Cache Driver Information Aggregator
 */
class CompositeDriverInformation extends AbstractDriverInformation
{
    /**
     * @var DriverInformationInterface
     */
    protected $driverInformation;

    /**
     * @var DriverInformationFactory
     */
    protected $driverInformationFactory;

    /**
     * @return DriverInterface[]
     */
    protected $drivers;

    /**
     * Dynamically pass method calls to the target.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->getCacheDriverInformation()->{$method}(...$parameters);
    }

    /**
     * @return DriverInformationInterface
     */
    public function getCacheDriverInformation(): DriverInformationInterface
    {
        if ($this->driverInformation === null) {
            $this->driverInformation = $this->getDriverInformationFactory()->create(
                ($this->getFirstPersistentDriver() ?? $this->getFirstDriver()),
                $this->getPoolNamespace()
            );
        }

        return $this->driverInformation;
    }

    /**
     * {@inheritdoc}
     */
    public function isDriverAggregator(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isDriverSupported(): bool
    {
        return false;
    }

    /**
     * @param DriverInterface           $driver
     * @param ?string                   $poolNamespace
     * @param ?DriverInformationFactory $driverInformationFactory
     */
    protected function __construct(
        DriverInterface $driver,
        ?string $poolNamespace,
        ?DriverInformationFactory $driverInformationFactory = null
    ) {
        $this->driver = $driver;
        $this->poolNamespace = $poolNamespace;
        $this->driverInformationFactory = $driverInformationFactory;
    }

    /**
     * @param  CompositeDriver $composite
     * @throws RuntimeException If the drivers can not be extracted.
     * @return DriverInterface[]
     */
    protected function extractAllDrivers(): array
    {
        $reflection = new ReflectionProperty($composite, 'drivers');
        $reflection->setAccessible(true);

        $drivers = $reflection->getValue($composite);

        if (is_array($drivers) && $drivers) {
            return $drivers;
        }

        throw new RuntimeException(
            'Unable to extract drivers from composite driver'
        );
    }

    /**
     * @return DriverInterface[]
     */
    protected function getAllDrivers(): array
    {
        if ($this->drivers === null) {
            $this->drivers = $this->extractAllDrivers();
        }

        return $this->drivers;
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

    /**
     * @return DriverInterface
     */
    protected function getFirstDriver(): DriverInterface
    {
        foreach ($this->getAllDrivers() as $driver) {
            return $driver;
        }
    }

    /**
     * @param  DriverInterface[] $drivers
     * @return ?DriverInterface
     */
    protected function getFirstPersistentDriver(): ?DriverInterface
    {
        foreach ($this->getAllDrivers() as $driver) {
            if ($driver->isPersistent()) {
                return $driver;
            }
        }

        return null;
    }

    /**
     * @return DriverInterface[]
     */
    protected function getPersistentDrivers(): array
    {
        $drivers = [];
        foreach ($this->getAllDrivers() as $driver) {
            if ($driver->isPersistent()) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }

    /**
     * @return DriverInterface[]
     */
    protected function getTransientDrivers(): array
    {
        $drivers = [];
        foreach ($this->getAllDrivers() as $driver) {
            if (!$driver->isPersistent()) {
                $drivers[] = $driver;
            }
        }

        return $drivers;
    }
}
