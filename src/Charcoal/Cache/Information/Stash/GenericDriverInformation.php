<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use Stash\Interfaces\DriverInterface;

/**
 * Generic Stash Cache Driver Information Aggregator
 *
 * Used for transient or unsupported drivers.
 */
class GenericDriverInformation extends AbstractDriverInformation
{
    /**
     * {@inheritdoc}
     */
    public function isDriverSupported(): bool
    {
        return false;
    }

    /**
     * @param DriverInterface $driver
     * @param ?string         $poolNamespace
     */
    protected function __construct(
        DriverInterface $driver,
        ?string $poolNamespace
    ) {
        $this->driver = $driver;
        $this->poolNamespace = $poolNamespace;
    }
}
