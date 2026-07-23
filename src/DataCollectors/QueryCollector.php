<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Collects database query information
 *
 * Tracks SQL queries, execution time, bindings, and stack traces.
 * This class serves as a singleton tracker that PDO wrappers can report to.
 */
class QueryCollector implements CollectorInterface
{
    private static ?self $instance = null;

    /** @var array<int, array<string, mixed>> */
    private array $queries   = [];

    private bool $collecting = false;

    public static function getInstance(): self
    {
        if (!self::$instance instanceof QueryCollector) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function start(): void
    {
        $this->collecting = true;
    }

    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Track a database query
     *
     * @param string $sql SQL query
     * @param array<int|string, mixed> $bindings Query bindings
     * @param float $time Execution time in milliseconds
     */
    public function trackQuery(string $sql, array $bindings, float $time): void
    {
        if (!$this->collecting) {
            return;
        }

        $this->queries[] = [
            'sql'       => $sql,
            'bindings'  => $bindings,
            'time'      => round($time, 2),
            'backtrace' => $this->getRelevantBacktrace(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'queries'    => $this->queries,
            'count'      => count($this->queries),
            'total_time' => round(array_sum(array_column($this->queries, 'time')), 2),
        ];
    }

    public function getName(): string
    {
        return 'queries';
    }

    public function getBadgeCount(): ?int
    {
        $count = count($this->queries);

        return $count > 0 ? $count : null;
    }

    /**
     * Get relevant stack trace (filter out DevToolbar internals)
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRelevantBacktrace(): array
    {
        /** @phpstan-ignore ekinoBannedCode.function */
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Filter out DevToolbar and PDO internals
        $filtered = array_filter($trace, function (array $frame): bool {
            $file = $frame['file'] ?? '';
            return !str_contains($file, 'DevToolbar')
                && !str_contains($file, 'PDO')
                && !str_contains($file, 'vendor/');
        });

        // Return up to 5 most relevant frames
        return array_slice(array_values($filtered), 0, 5);
    }

    /**
     * Reset queries (for testing)
     */
    public function reset(): void
    {
        $this->queries    = [];
        $this->collecting = false;
    }
}
