<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Stash\Interfaces\DriverInterface;

/**
 * Cache Driver Information
 */
interface DriverInformationInterface
{
    /**
     * @return DriverInterface
     */
    public function getDriver(): DriverInterface;

    /**
     * Retrieves the pool namespace.
     *
     * @return ?string
     */
    public function getPoolNamespace(): ?string;

    /**
     * Determines if the driver aggregates (chains or composites) other drivers.
     *
     * @return bool
     */
    public function isDriverAggregator(): bool;

    /**
     * Determines if the driver is supported by the information aggregator.
     *
     * @return bool
     */
    public function isDriverSupported(): bool;
}
