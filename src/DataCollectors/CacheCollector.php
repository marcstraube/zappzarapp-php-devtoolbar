<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;
use Redis;
use Throwable;

/**
 * Cache Collector
 *
 * Monitors Redis/Cache operations (get, set, delete, hits, misses)
 */
class CacheCollector implements CollectorInterface
{
    use BacktraceTrait;

    /** @var array<int, array<string, mixed>> */
    private array $operations = [];

    private int $hits         = 0;

    private int $misses       = 0;

    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PDepend crashes on property hooks */
    private bool $collecting  = false;

    /**
     * Start collecting cache operations
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->operations = [];
        $this->hits       = 0;
        $this->misses     = 0;
    }

    /**
     * Stop collecting cache operations
     */
    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'cache';
    }

    public function reset(): void
    {
        $this->operations = [];
        $this->hits       = 0;
        $this->misses     = 0;
        $this->collecting = false;
    }

    public function getBadgeCount(): ?int
    {
        $count = count($this->operations);

        return $count > 0 ? $count : null;
    }

    /**
     * Get collected cache operation data
     */
    public function getData(): array
    {
        $total   = $this->hits + $this->misses;
        $hitRate = $total > 0 ? round(($this->hits / $total) * 100, 1) : 0;

        return [
            'operations' => $this->operations,
            'hits'       => $this->hits,
            'misses'     => $this->misses,
            'hit_rate'   => $hitRate,
            'total_time' => round(array_sum(array_column($this->operations, 'time')), 2),
            'count'      => count($this->operations),
        ];
    }

    /**
     * Track a cache operation
     *
     * @param string $type Operation type (get, set, delete)
     * @param string $key Cache key
     * @param float $time Execution time in milliseconds
     * @param mixed $value Result value (get: cached value or false, set: value stored, delete: success)
     * @param int|null $ttl TTL in seconds (for set operations)
     */
    public function trackOperation(
        string $type,
        string $key,
        float $time,
        mixed $value = null,
        ?int $ttl = null
    ): void {
        if (!$this->collecting) {
            return;
        }

        $operation = [
            'type'      => $type,
            'key'       => $key,
            'time'      => round($time, 2),
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        if ($type === 'get') {
            $hit              = $value !== false && $value !== null;
            $operation['hit'] = $hit;
            if ($hit) {
                $this->hits++;
                $operation['value'] = $this->filterValue($value);
            } else {
                $this->misses++;
            }
        } elseif ($type === 'set') {
            $operation['ttl']  = $ttl;
            $operation['size'] = $this->calculateSize($value);
        } elseif ($type === 'delete') {
            $operation['success'] = (bool)$value;
        }

        $this->operations[] = $operation;
    }

    /**
     * Wrapper for Redis GET operation
     *
     * @noinspection PhpUnused Called by application code wrapping Redis calls
     *
     * @param Redis $redis Redis instance
     * @param string $key Cache key
     * @return mixed Cached value or false
     */
    public function wrapRedisGet(Redis $redis, string $key): mixed
    {
        if (!$this->collecting) {
            return $redis->get($key);
        }

        $start  = hrtime(true);
        $result = $redis->get($key);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $this->trackOperation('get', $key, $time, $result);

        // Store TTL for hits
        if ($result !== false) {
            $ttl = $redis->ttl($key);
            if ($ttl > 0) {
                $lastOp        = &$this->operations[count($this->operations) - 1];
                $lastOp['ttl'] = $ttl;
            }
        }

        return $result;
    }

    /**
     * Wrapper for Redis SET operation
     *
     * @noinspection PhpUnused Called by application code wrapping Redis calls
     *
     * @param Redis $redis Redis instance
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds
     * @return bool Success status
     */
    public function wrapRedisSet(Redis $redis, string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->collecting) {
            if ($ttl !== null) {
                return $redis->setex($key, $ttl, $value);
            }

            return $redis->set($key, $value);
        }

        $start  = hrtime(true);
        $result = $ttl !== null ? $redis->setex($key, $ttl, $value) : $redis->set($key, $value);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $this->trackOperation('set', $key, $time, $value, $ttl);

        return $result;
    }

    /**
     * Wrapper for Redis DELETE operation
     *
     * @noinspection PhpUnused Called by application code wrapping Redis calls
     *
     * @param Redis $redis Redis instance
     * @param string|array<string> $key Cache key(s)
     * @return int Number of keys deleted
     */
    public function wrapRedisDelete(Redis $redis, string|array $key): int
    {
        if (!$this->collecting) {
            return $redis->del($key);
        }

        $start  = hrtime(true);
        $result = $redis->del($key);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $keyString = is_array($key) ? implode(', ', $key) : $key;
        $this->trackOperation('delete', $keyString, $time, $result);

        return $result;
    }


    /**
     * Filter sensitive data from cached values
     *
     * @param mixed $value Value to filter
     * @return mixed Filtered value
     */
    private function filterValue(mixed $value): mixed
    {
        if ($value === false || $value === null) {
            return $value;
        }

        // Try to unserialize if serialized
        if (is_string($value) && $this->isSerializedString($value)) {
            $value = $this->safeUnserialize($value);
        }

        if (is_string($value)) {
            return $this->filterString($value);
        }

        if (is_array($value)) {
            return $this->filterArray($value);
        }

        return $value;
    }

    /**
     * Check if string appears to be serialized
     */
    private function isSerializedString(string $value): bool
    {
        return str_starts_with($value, 'a:')
            || str_starts_with($value, 'O:')
            || str_starts_with($value, 's:');
    }

    /**
     * Safely unserialize a value without error suppression
     *
     * Object injection guard: allowed_classes is false, so payloads are
     * decoded to arrays/scalars and __wakeup()/__destruct() gadget chains
     * are never instantiated. The toolbar only needs a displayable
     * structure — any serialized object surfaces as __PHP_Incomplete_Class,
     * which the downstream filter renders harmlessly. This matters because
     * isSerializedString() also feeds in plain cache strings that merely
     * begin with 'O:', which may be attacker-controlled.
     *
     * Not dead code (the try/catch): unserialize() emits E_WARNING on
     * malformed input, and consumers may install error handlers that turn
     * warnings into ErrorException — the catch keeps foreign or truncated
     * cache entries from breaking the page render.
     */
    private function safeUnserialize(string $value): mixed
    {
        try {
            $unserialized = unserialize($value, ['allowed_classes' => false]);
        // @phpstan-ignore catch.neverThrown (consumer error handlers may convert E_WARNING to ErrorException)
        } catch (Throwable) {
            return $value;
        }

        return $unserialized !== false ? $unserialized : $value;
    }

    /**
     * Filter sensitive data from string values
     */
    private function filterString(string $value): string
    {
        // Truncate long strings
        if (strlen($value) > 1000) {
            return substr($value, 0, 1000) . '... (truncated)';
        }

        // Filter sensitive patterns
        $filtered = preg_replace('/("password"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $value) ?? $value;

        return preg_replace('/("token"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $filtered) ?? $filtered;
    }

    /**
     * Filter sensitive data from array values
     *
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function filterArray(array $value): array
    {
        foreach (array_keys($value) as $key) {
            if (in_array(strtolower((string)$key), ['password', 'token', 'secret', 'api_key'], true)) {
                $value[$key] = '[FILTERED]';
            }
        }

        // Truncate large arrays
        if (count($value) > 50) {
            $value                = array_slice($value, 0, 50, true);
            $value['__truncated'] = '... (' . (count($value) - 50) . ' more items)';
        }

        return $value;
    }

    /**
     * Calculate size of cached value
     *
     * @param mixed $value Value to measure
     * @return string Human-readable size
     */
    private function calculateSize(mixed $value): string
    {
        $serialized = serialize($value);
        $bytes      = strlen($serialized);
        if ($bytes < 1024) {
            return $bytes . 'B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . 'KB';
        }

        return round($bytes / (1024 * 1024), 1) . 'MB';
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }
}
