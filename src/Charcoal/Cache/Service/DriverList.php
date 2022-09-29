<?php

namespace Charcoal\Cache\Service;

use RuntimeException;

// From 'tedivm/stash'
use Stash\DriverList as StashDriverList;
use Stash\Interfaces\DriverInterface;

/**
 * Driver List
 *
 * Extends the base list to encourage lowercase/generic driver names.
 */
class DriverList extends StashDriverList
{
    /**
     * A map of aliases for possible {@see StashDriverList::$drivers cache drivers}.
     *
     * @var array<string, string>
     */
    protected static $aliases = [
        'apc'        => 'Apc',
        'array'      => 'Ephemeral',
        'composite'  => 'Composite',
        'filesystem' => 'FileSystem',
        'memcache'   => 'Memcache',
        'noop'       => 'BlackHole',
        'redis'      => 'Redis',
        'sqlite'     => 'SQLite',
    ];

    /**
     * A map of {@see self::$aliases cache driver aliases} deprecated in v0.3
     * (originally defined in the "cache/drivers" service) and planned to be
     * removed in v0.4.
     *
     * @var array<string, string>
     */
    protected static $deprecatedAliases = [
        'db'     => 'sqlite',
        'file'   => 'filesystem',
        'memory' => 'array',
    ];

    /**
     * Returns a list of cache drivers that are also supported by this system.
     *
     * @return array<string, string> Driver Name => Class Name
     */
    public static function getAvailableDrivers(): array
    {
        $availableDrivers = [];
        $registeredDrivers = self::getAllDrivers();
        foreach ($registeredDrivers as $name => $class) {
            if ($name === 'composite') {
                $availableDrivers[$name] = $class;
            } else {
                if ($class::isAvailable()) {
                    $availableDrivers[$name] = $class;
                }
            }
        }

        return $availableDrivers;
    }

    /**
     * Returns a list of all registered cache drivers,
     * regardless of system support.
     *
     * @return array<string, string> Driver Name => Class Name
     */
    public static function getAllDrivers(): array
    {
        $driverList = [];
        foreach (self::$drivers as $name => $class) {
            if (
                !class_exists($class) ||
                !in_array('Stash\Interfaces\DriverInterface', class_implements($class))
            ) {
                continue;
            }

            $driverList[$name] = $class;
        }

        return $driverList;
    }

    /**
     * Returns the driver name for a specific driver alias.
     *
     * @param  string $alias
     * @return ?string
     */
    public static function getDriverName(string $alias): ?string
    {
        // Resolve deprecated aliases
        if (isset(self::$deprecatedAliases[$alias])) {
            trigger_error(
                sprintf(
                    'Driver alias "%1$s" is deprecated since %3$s. '.
                    'Use "%2$s" instead.',
                    $alias,
                    self::$deprecatedAliases[$alias],
                    '0.3.0'
                ),
                E_USER_DEPRECATED
            );
            $alias = self::$deprecatedAliases[$alias];
        }

        // Resolve registered aliases
        if (isset(self::$aliases[$alias])) {
            return self::$aliases[$alias];
        }

        return null;
    }

    /**
     * Returns the driver class for a specific driver name or alias.
     *
     * This method is not type-hinted to maintain compatibility
     * with parent class.
     *
     * This method overrides the parent method to:
     *
     * - support driver aliases
     * - return NULL instead of FALSE
     *
     * @param  string $name
     * @return ?string
     */
    public static function getDriverClass($name)
    {
        $name = (self::getDriverName($name) ?? $name);

        if (isset(self::$drivers[$name])) {
            return self::$drivers[$name];
        }

        return null;
    }

    /**
     * Registers a new alias for a driver.
     *
     * @param  string $alias
     * @param  string $name
     * @throws InvalidArgumentException If the driver is not registered.
     * @throws InvalidArgumentException If the name is already used as a driver.
     * @retuen void
     */
    public static function registerAlias(string $alias, string $name): void
    {
        if (isset(self::$deprecatedAliases[$alias])) {
            unset(self::$deprecatedAliases[$alias]);
        }

        if (!parent::getDriverClass($name)) {
            throw new InvalidArgumentException(sprintf(
                'Driver "%s" is not registered',
                $name
            ));
        }

        if (parent::getDriverClass($alias)) {
            throw new InvalidArgumentException(sprintf(
                'Alias "%s" is unavailable: currently used as a driver name',
                $alias
            ));
        }

        self::$aliases[$alias] = $name;
    }

    /**
     * Prevent instantiation of driver list.
     */
    private function __construct()
    {
    }
}
