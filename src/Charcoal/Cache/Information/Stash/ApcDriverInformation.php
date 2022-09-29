<?php

declare(strict_types=1);

namespace Charcoal\Cache\Information\Stash;

use APCUIterator;
use APCIterator;
use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stash\Driver\Apc as ApcDriver;

/**
 * Stash APC Cache Driver Information Aggregator
 */
class ApcDriverInformation extends AbstractDriverInformation
{
    /**
     * Whether the Stash driver {@see \Stash\Driver\Apc::$apcu uses APCu or APC}.
     *
     * @var bool
     */
    protected $apcu;

    /**
     * The Stash APC driver {@see \Stash\Driver\Apc::$apcNamespace namespace}.
     *
     * This stores the data under a namespace in case other scripts are using
     * APC to store data as well.
     *
     * @var string
     */
    protected $apcNamespace;

    /**
     * A closure for the {@see \Stash\Driver\Apc::makeKey() key maker method}
     * from the Stash driver.
     *
     * @var Closure
     */
    protected $keyMaker;

    /**
     * Regular expression pattern to match a Stash / APC cache key.
     *
     * @var ?string
     */
    protected $regexpParseCacheKeyPattern;

    /**
     * @return Memcache|Memcached
     */
    public function getApcNamespace()
    {
        if ($this->apcNamespace === null) {
            $this->apcNamespace = $this->extractApcNamespace();
        }

        return $this->apcNamespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheItems($search = null): iterable
    {
        $iterator = $this->createApcIterator($search);

        foreach ($iterator as $item) {
            yield $this->formatCacheItem($item);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheSummary($search = null): array
    {
        $summary = $this->getBaseCacheSummary($search);

        $iterator = $this->createApcIterator($search);

        $summary['totalCount'] = $iterator->getTotalCount();
        $summary['totalHits']  = $iterator->getTotalHits();
        $summary['totalSize']  = $iterator->getTotalSize();

        return $summary;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return ($this->isDriverUsingApcu() ? 'APCu' : 'APC');
    }

    /**
     * {@inheritdoc}
     */
    public function isDriverSupported(): bool
    {
        return true;
    }

    /**
     * @param ApcDriver $driver
     * @param ?string   $poolNamespace
     */
    protected function __construct(
        ApcDriver $driver,
        ?string $poolNamespace
    ) {
        $this->driver = $driver;
        $this->poolNamespace = $poolNamespace;
    }

    /**
     * Determines if the cache item key nodes are valid.
     *
     * @param  array $nodes The cache item key nodes to test.
     * @return bool
     */
    protected function areCacheItemKeyNodesValid(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (strlen($node) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Asserts that the cache item key nodes are valid, throws an Exception if not.
     *
     * @param  array $nodes The cache item key nodes to test.
     * @throws InvalidArgumentException If the key is invalid.
     * @return void
     */
    protected function assertValidCacheItemKeyNodes(array $nodes): void
    {
        if (!$this->areCacheItemKeyNodesValid($nodes)) {
            throw new InvalidArgumentException(
                'Invalid or empty node in cache item key'
            );
        }
    }

    /**
     * Composes the APC cache item key.
     *
     * This method will prefix the key with the
     * {@see \Stash\Pool::getItem() pool namespace} and the
     * {@see \Stash\Item::setKey() item namespace}.
     *
     * @param  ?string $key The cache key to compose.
     * @return string
     */
    protected function composeCacheItemKey(string $key = null): string
    {
        $poolNamespace = $this->getPoolNamespace();

        if (is_string($key)) {
            $nodes = explode('/', trim($key, '/'));

            if ($poolNamespace) {
                array_unshift($nodes, PoolInformation::DEFAULT_ITEM_DATA_NAMESPACE, $poolNamespace);
            } else {
                array_unshift($nodes, PoolInformation::DEFAULT_ITEM_DATA_NAMESPACE);
            }
        } else {
            $nodes = [ PoolInformation::DEFAULT_ITEM_DATA_NAMESPACE ];

            if ($poolNamespace) {
                $nodes[] = $poolNamespace;
            }
        }

        $this->assertValidCacheItemKeyNodes($nodes);

        $nodes = array_map('preg_quote', $nodes);

        return $this->makeKey($nodes);
    }

    /**
     * @param  ?(string|string[]) $search The cache item search query.
     * @throws RuntimeException If the APCUIterator or APCIterator classes are missing.
     * @return APCUIterator|APCIterator
     */
    protected function createApcIterator($search = null)
    {
        if ($this->isDriverUsingApcu()) {
            if (class_exists('\\APCUIterator', false)) {
                return new APCUIterator($this->formatSearchItemQuery($search));
            }

            throw new RuntimeException(
                'APCUIterator could be found'
            );
        }

        if (class_exists('\\APCIterator', false)) {
            return new APCIterator('user', $this->formatSearchItemQuery($search));
        }

        throw new RuntimeException(
            'APCIterator could be found'
        );
    }

    /**
     * @throws RuntimeException If the APC namespace can not be extracted.
     * @return string
     */
    protected function extractApcNamespace(): string
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionProperty($driver, 'apcNamespace');
        $reflection->setAccessible(true);

        $apcNamespace = $reflection->getValue($driver);

        if (is_string($apcNamespace)) {
            return $apcNamespace;
        }

        throw new RuntimeException(
            'Unable to extract APC namespace from driver'
        );
    }

    /**
     * @throws RuntimeException If the APC variant can not be extracted.
     * @return bool
     */
    protected function extractApcVariant(): bool
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionProperty($driver, 'apcu');
        $reflection->setAccessible(true);

        $apcu = $reflection->getValue($driver);

        if (is_bool($apcu)) {
            return $apcu;
        }

        throw new RuntimeException(
            'Unable to extract APC/APCu flag from driver'
        );
    }

    /**
     * @return Closure
     */
    protected function extractKeyMaker(): Closure
    {
        $driver = $this->getDriver();

        $reflection = new ReflectionMethod($driver, 'makeKey');
        $reflection->setAccessible(true);

        return $reflection->getClosure($driver);
    }

    /**
     * Formats the cache item information.
     *
     * @see https://www.php.net/manual/en/function.apcu-cache-info.php
     *     Example of item structure.
     *
     * @param  array<string, mixed> $item The cache item.
     *     ```php
     *     [
     *         'type'          => 'user',
     *         'value'         => …,
     *         'num_hits'      => 0,
     *         'mtime'         => 1652381705,
     *         'creation_time' => 1652381705,
     *         'deletion_time' => 0,
     *         'access_time'   => 1652381705,
     *         'ref_count'     => 0,
     *         'mem_size'      => 10280,
     *         'ttl'           => 431243,
     *     ]
     *     ```
     * @return array<string, mixed>
     */
    protected function formatCacheItem(array $item): array
    {
        $output = $this->getBaseCacheItem();

        $keys = $this->parseCacheItemKeyPattern($item['key']);

        $output['type']           = $keys['stashNS'];
        $output['value']          = $item['value'];
        $output['key']            = $item['key'];
        $output['formattedKey']   = $this->formatParsedCacheItemKey($keys);
        $output['creationTime']   = $item['creation_time'];
        $output['modifiedTime']   = $item['mtime'];
        $output['deletionTime']   = $item['deletion_time'];
        $output['accessTime']     = $item['access_time'];
        $output['expirationTime'] = ($item['creation_time'] + $item['ttl']);
        $output['ttl']            = $item['ttl'];
        $output['hits']           = $item['num_hits'];
        $output['size']           = $item['mem_size'];

        return $output;
    }

    /**
     * Formats the APC key into a human-readable identifier.
     *
     * @param  string $key The cache item key to format.
     * @return string
     */
    protected function formatCacheItemKey(string $key): string
    {
        $parts = $this->parseCacheItemKeyPattern($key);

        if ($parts) {
            return $this->formatParsedCacheItemKey($parts);
        }

        return $key;
    }

    /**
     * Formats the APC key into a human-readable identifier.
     *
     * @param  array<string, string> $parts The cache item key parts to format.
     * @return string
     */
    protected function formatParsedCacheItemKey(array $parts): string
    {
        $key = trim($parts['itemID'], ':');
        $key = preg_replace(
            [
                '/:+/',
                '/\.+/',
            ],
            [
                ' ⇒ ',
                '/',
            ],
            $key
        );

        return $key;
    }

    /**
     * Formats the RegExp pattern to match a Stash / APC cache key.
     *
     * Breakdown:
     *
     * - `apcID`: APC driver installation ID.
     * - `apcNS`: Optional application key or installation ID.
     * - `stashNS`: Stash cache type, either "cache" (for data) or "sp" (for stampede).
     * - `poolNS`: Optional application key.
     * - `itemID`: Data segment.
     *
     * @param  string $apcNamespace  The Stash namespace used to segment different applications.
     * @param  string $poolNamespace
     * @return string
     */
    protected function formatRegexpParseCacheKeyPattern(
        string $apcNamespace,
        string $poolNamespace
    ): string {
        $pattern  = '/^';
        $pattern .= '(?<apcID>[a-f0-9]{32})::';
        $pattern .= '(?:(?<apcNS>'.preg_quote($apcNamespace).'|[a-f0-9]{32})::)?';
        $pattern .= '(?<stashNS>cache|sp)::';
        $pattern .= '(?:(?<poolNS>'.preg_quote($poolNamespace).')::)?';
        $pattern .= '(?<itemID>.+)';
        $pattern .= '$/i';

        return $pattern;
    }

    /**
     * Formats the APC key or key names into a search pattern.
     *
     * @param  ?(string|string[]) $search The cache item search query to format.
     * @return string
     */
    protected function formatSearchItemQuery($search = null): string
    {
        // Assume the search string is a regular expression pattern
        // if it contains any special characters or default delimiters.
        if (is_string($search) && preg_match('/[\:\^\$\!\?\{\}]|^\/.+\/$/', $search)) {
            return $search;
        }

        return '/'.$this->formatSearchItemQueryKeys($search).'/';
    }

    /**
     * Formats the APC key or key names.
     *
     * @param  ?(string|string[]) $search The cache item search query to format.
     * @throws InvalidArgumentException If the key names are invalid.
     * @return string
     */
    protected function formatSearchItemQueryKeys($search = null): string
    {
        if ($search === null) {
            return '^'.$this->composeCacheItemKey();
        }

        if (is_string($search)) {
            return '^'.$this->composeCacheItemKey($search);
        }

        if (is_array($search)) {
            return '^'.implode('|^', array_map(
                [ $this, 'composeCacheItemKey' ],
                $search
            ));
        }

        throw new InvalidArgumentException(
            'Expected APC key name or array of key names'
        );
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
     * Retrieves the RegExp pattern to match a Stash / APC cache key.
     *
     * @see self::formatRegexpParseCacheKeyPattern()
     *     For pattern details.
     *
     * @return ?string
     */
    protected function getRegexpParseCacheKeyPattern(): ?string
    {
        if ($this->regexpParseCacheKeyPattern === null) {
            $apcNamespace  = $this->getApcNamespace();
            $poolNamespace = $this->getPoolNamespace();

            if ($apcNamespace && $poolNamespace) {
                $this->regexpParseCacheKeyPattern = $this->formatRegexpParseCacheKeyPattern(
                    $apcNamespace,
                    $poolNamespace
                );
            }
        }

        return $this->regexpParseCacheKeyPattern;
    }

    /**
     * @return bool
     */
    protected function isDriverUsingApcu()
    {
        if ($this->apcu === null) {
            $this->apcu = $this->extractApcVariant();
        }

        return $this->apcu;
    }

    /**
     * Alias of {@see \Stash\Driver\Apc::makeKey()}.
     *
     * This method will include the pool namespace.
     *
     * @param  array $key
     * @return string
     */
    protected function makeKey(array $key): string
    {
        $makeKey = $this->getKeyMaker();
        return $makeKey($key);
    }

    /**
     * Parses the Stash / APC cache key and returns its components.
     *
     * @param  string $key The cache item key to parse.
     * @return ?array
     */
    protected function parseCacheItemKeyPattern(string $key): ?array
    {
        $pattern = $this->getRegexpParseCacheKeyPattern();
        if (preg_match(
            $pattern,
            $key,
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            return [
                'apcID'   => $matches['apcID'],
                'apcNS'   => $matches['apcNS'],
                'stashNS' => $matches['stashNS'],
                'poolNS'  => $matches['poolNS'],
                'itemID'  => $matches['itemID'],
            ];
        }

        return null;
    }
}
