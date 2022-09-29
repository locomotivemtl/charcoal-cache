<?php

namespace Charcoal\Cache\Factory;

use RuntimeException;

// From 'tedivm/stash'
use Stash\DriverList as StashDriverList;
use Stash\Interfaces\DriverInterface;

// From 'charcoal-cache'
use Charcoal\Cache\Service\DriverList;

/**
 * Driver Factory
 */
class DriverFactory
{
    /**
     * A map of aliases for possible {@see StashDriverList::$drivers cache drivers}.
     *
     * @var array<string, string>
     */
    protected $aliases = [
        'apc'        => 'Apc',
        'array'      => 'Ephemeral',
        'chain'      => 'Composite',
        'filesystem' => 'FileSystem',
        'memcache'   => 'Memcache',
        'null'       => 'BlackHole',
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
    protected $deprecatedAliases = [
        'db'     => 'sqlite',
        'file'   => 'filesystem',
        'memory' => 'array',
        'noop'   => 'null',
    ];

    /**
     * The temporary reference of supported
     * {@see StashDriverList::getAvailableDrivers() cache drivers}.
     *
     * @var ?array<string, string>
     */
    protected $availableDrivers;

    /**
     * The temporary reference of all registered
     * {@see StashDriverList::getAllDrivers() cache drivers}.
     *
     * @var ?array<string, string>
     */
    protected $registeredDrivers;

    /**
     * Creates a driver.
     *
     * If given multiple cache names and options, creates a composite driver
     * wrapping the specified drivers.
     *
     * @param  string|string[]      $names   One or more driver names or aliases.
     * @param  array<string, array> $options Map of options for drivers.
     * @return DriverInterface
     */
    public function create($names, array $options): DriverInterface
    {
        $this->warmUpDriverList();

        if (!is_array($names)) {
            if (!isset($options[$names])) {
                $options[$names] = [
                    $names => $options,
                ];
            }

            $names = [ $names ];
        }

        $drivers = [];

        foreach ($names as $name) {
            $drivers[] = $this->createDriver($name, $options);

            /*
            $alias = ($this->deprecatedAliases[$alias] ?? null);

            $driverName = ($this->getDriverName($name) ?? $name);

            $this->assertValidDriver($driverName, $this->availableDrivers, $this->registeredDrivers);

            $driverClass = $this->availableDrivers[$driverName];

            $driverOptions = $this->parseDriverOptions($driverName, (
                $this->extractDriverOptions($options, $name, $alias, $driverName) ?? []
            ));

            $driver = new $driverClass($driverOptions);
            $drivers[] = $driver;
            */
        }

        if (count($drivers) === 1) {
            $this->flushDriverList();

            return reset($drivers);
        }

        if (isset($options['chain'])) {
            $options['chain']['drivers'] = $drivers;
        } else {
            $options['chain'] = [ 'drivers' => $drivers ];
        }

        $driver = $this->createDriver('chain', $options);

        /*
        $this->assertValidDriver('Composite', $this->availableDrivers, $this->registeredDrivers);

        if (!isset($options['Composite'])) {
            $options['Composite'] = [];
        }

        $options['Composite'] = [
            'drivers' => $drivers,
        ] + ($options['Composite'] ?? []);

        $options['Composite'] = $this->parseDriverOptions('Composite', $options['Composite']);

        $class  = $this->availableDrivers['Composite'];
        $driver = new $class($options['Composite']);
        */

        $this->flushDriverList();

        return $driver;
    }

    /**
     * Returns the driver name for a specific driver alias.
     *
     * @param  string $alias
     * @return ?string
     */
    public function getDriverName(string $alias): ?string
    {
        // Resolve deprecated aliases
        if (isset($this->deprecatedAliases[$alias])) {
            trigger_error(
                sprintf(
                    'Driver alias "%1$s" is deprecated since %3$s. '.
                    'Use "%2$s" instead.',
                    $alias,
                    $this->deprecatedAliases[$alias],
                    '0.3.0'
                ),
                E_USER_DEPRECATED
            );
            $alias = $this->deprecatedAliases[$alias];
        }

        // Resolve registered aliases
        if (isset($this->aliases[$alias])) {
            return $this->aliases[$alias];
        }

        return null;
    }

    /**
     * Returns the driver class for a specific driver name or alias.
     *
     * @param  string $alias
     * @return ?string
     */
    public function getDriverClass(string $alias): ?string
    {
        $name = ($this->getDriverName($alias) ?? $alias);

        $class = DriverList::getDriverClass($name);
        if ($class) {
            return $class;
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
    public function registerAlias(string $alias, string $name): void
    {
        if (isset(self::$deprecatedAliases[$alias])) {
            unset(self::$deprecatedAliases[$alias]);
        }

        if (!DriverList::getDriverClass($name)) {
            throw new InvalidArgumentException(sprintf(
                'Driver "%s" is not registered',
                $name
            ));
        }

        if (DriverList::getDriverClass($alias)) {
            throw new InvalidArgumentException(sprintf(
                'Alias "%s" is unavailable: currently used as a driver name',
                $alias
            ));
        }

        $this->aliases[$alias] = $name;
    }

    /**
     * Tries to parse cache driver options, if a method is available.
     *
     * @param  string $name
     * @param  array  $options
     * @return array
     */
    public function parseDriverOptions(string $name, array $options): array
    {
        $parseOptions = 'parse'.$name.'Options';
        if (isset($options) && is_callable([ $this, $parseOptions ])) {
            $options = $this->{$parseOptions}($options);
        }

        return $options;
    }

    /**
     * Parses Memcache driver options.
     *
     * @param  array $options
     * @return array
     */
    public function parseMemcacheOptions(array $options): array
    {
        // Fix servers spec since underlying drivers expect plain arrays, not hashes.
        $servers = [];
        foreach ($options['servers'] as $serverSpec) {
            $servers[] = [
                $serverSpec['server'],
                $serverSpec['port'],
                ($serverSpec['weight'] ?? null)
            ];
        }

        $options['servers'] = $servers;

        return $options;
    }

    /**
     * Asserts that the cache driver is available, throws an Exception if not.
     *
     * @param  string                $name
     * @param  array<string, string> $availableDrivers
     * @param  array<string, string> $registeredDrivers
     * @throws RuntimeException If a driver is unavailable or does not exist.
     * @return void
     */
    protected function assertValidDriver(
        string $name,
        array $availableDrivers,
        array $registeredDrivers
    ): void {
        $this->warmUpDriverList();

        if (!isset($this->availableDrivers[$name])) {
            if (isset($this->registeredDrivers[$name])) {
                throw new RuntimeException(sprintf(
                    'Driver "%s" currently unavailable',
                    $name
                ));
            }

            throw new RuntimeException(sprintf(
                'Driver "%s" does not exist',
                $name
            ));
        }
    }

    /**
     * Internally creates a driver.
     *
     * @param  string               $names   A driver name or alias.
     * @param  array<string, array> $options Map of options for drivers.
     * @return DriverInterface
     */
    protected function createDriver(string $name, array $options): DriverInterface
    {
        $driverName = ($this->getDriverName($name) ?? $name);

        $this->assertValidDriver($driverName);

        $driverClass = $this->availableDrivers[$driverName];

        $keys = [ $name ];

        if (isset($this->deprecatedAliases[$alias])) {
            $keys[] = $this->deprecatedAliases[$alias];
        }

        if ($driverName !== $name) {
            $keys[] = $driverName;
        }

        $driverOptions = $this->parseDriverOptions($driverName, (
            $this->extractDriverOptions($options, ...$keys) ?? []
        ));

        return new $driverClass($driverOptions);
    }

    /**
     * Extracts the driver options for the given driver name or aliases.
     *
     * @param  array<string, array> $options  Map of driver options.
     * @param  ?string              ...$keys One or more driver names to lookup.
     * @return ?array
     */
    protected function extractDriverOptions(array $options, ?string ...$keys): ?array
    {
        foreach ($keys as $key) {
            if ($key && isset($options[$key])) {
                return $options[$key];
            }
        }

        return null;
    }

    /**
     * Flushes the internal reference to lists of supported and registered drivers.
     *
     * @return void
     */
    protected function flushDriverList(): void
    {
        $this->availableDrivers  = null;
        $this->registeredDrivers = null;
    }

    /**
     * Fetches the lists of supported and registered drivers for internal reference.
     *
     * @return void
     */
    protected function warmUpDriverList(): void
    {
        if (is_null($this->availableDrivers)) {
            $this->availableDrivers  = DriverList::getAvailableDrivers();
        }

        if (is_null($this->registeredDrivers)) {
            $this->registeredDrivers = DriverList::getAllDrivers();
        }
    }
}
