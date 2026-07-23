<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Analyzers;

/**
 * Query Analyzer
 *
 * Detects N+1 query problems and provides optimization suggestions
 */
class QueryAnalyzer
{
    private const int N_PLUS_ONE_THRESHOLD = 3; // Minimum repetitions to flag as N+1

    /**
     * Detect N+1 query patterns
     *
     * @param array<int, array<string, mixed>> $queries List of executed queries
     * @return array<int, array<string, mixed>> Detected N+1 patterns
     */
    public function detectNPlusOne(array $queries): array
    {
        $patterns  = [];
        $nPlusOnes = [];

        // Group queries by normalized pattern
        foreach ($queries as $query) {
            $sql     = $query['sql'] ?? '';
            $pattern = $this->normalizeQuery($sql);

            if (!isset($patterns[$pattern])) {
                $patterns[$pattern] = [
                    'pattern'    => $pattern,
                    'original'   => $sql,
                    'instances'  => [],
                    'total_time' => 0,
                ];
            }

            $patterns[$pattern]['instances'][] = $query;
            $patterns[$pattern]['total_time'] += $query['time'] ?? 0;
        }

        // Identify N+1 patterns (same pattern executed N times)
        foreach ($patterns as $pattern => $data) {
            if (count($data['instances']) >= self::N_PLUS_ONE_THRESHOLD) {
                $nPlusOnes[] = [
                    'pattern'    => $pattern,
                    'count'      => count($data['instances']),
                    'total_time' => round($data['total_time'], 2),
                    'avg_time'   => round($data['total_time'] / count($data['instances']), 2),
                    'instances'  => $data['instances'],
                    'location'   => $this->extractLocation($data['instances'][0]),
                    'suggestion' => $this->generateSuggestion($pattern, $data['instances']),
                ];
            }
        }

        // Sort by total time (most impactful first)
        usort($nPlusOnes, fn(array $a, array $b): int => $b['total_time'] <=> $a['total_time']);

        return $nPlusOnes;
    }

    /**
     * Normalize query for pattern matching
     *
     * Replaces specific values with placeholders to identify similar queries
     *
     * @param string $sql SQL query
     * @return string Normalized query pattern
     */
    private function normalizeQuery(string $sql): string
    {
        // Replace numbers with ?
        $sql = (string)preg_replace('/\b\d+\b/', '?', $sql);

        // Replace quoted strings with ?
        $sql = (string)preg_replace("/'[^']*'/", '?', $sql);
        $sql = (string)preg_replace('/"[^"]*"/', '?', $sql);

        // Replace IN clauses with placeholder
        $sql = (string)preg_replace('/IN\s*\([^)]*\)/i', 'IN (?)', $sql);

        // Normalize whitespace
        $sql = (string)preg_replace('/\s+/', ' ', $sql);

        return trim(strtoupper($sql));
    }

    /**
     * Extract location information from query
     *
     * @param array<string, mixed> $query Query data
     * @return string Location string
     */
    private function extractLocation(array $query): string
    {
        $backtrace = $query['backtrace'] ?? [];

        if (!empty($backtrace)) {
            $frame = $backtrace[0];
            $file  = $frame['file'] ?? 'unknown';
            $line  = $frame['line'] ?? 0;

            // Extract just the filename
            $filename = basename((string) $file);

            return sprintf('%s:%s', $filename, $line);
        }

        return 'unknown';
    }

    /**
     * Generate optimization suggestion for N+1 pattern
     *
     * @param string $pattern Normalized query pattern
     * @param array<int, array<string, mixed>> $instances Query instances
     * @return string Optimization suggestion
     */
    private function generateSuggestion(string $pattern, array $instances): string
    {
        // Extract table name
        $table = preg_match('/FROM\s+([a-z_]+)/i', $pattern, $matches) ? $matches[1] : 'table';

        // Check if it's a WHERE id = ? pattern
        if (preg_match('/WHERE\s+(\w+)\s*=\s*\?/i', $pattern, $matches)) {
            $column = $matches[1];

            // Extract actual values from instances
            $values     = $this->extractWhereValues($instances);
            $valuesList = $values === [] ? '1, 2, 3, ...' : implode(', ', array_slice($values, 0, 3));

            return sprintf(
                "Use a WHERE IN clause to fetch all records in a single query:\n" .
                "SELECT * FROM %s WHERE %s IN (%s)",
                $table,
                $column,
                $valuesList
            );
        }

        // Generic suggestion
        return sprintf(
            "Consider using a JOIN or eager loading to reduce %d queries to 1 query.\n" .
            "Look for opportunities to batch these queries together.",
            count($instances)
        );
    }

    /**
     * Extract WHERE clause values from query instances
     *
     * @param array<int, array<string, mixed>> $instances Query instances
     * @return array<int, string> Extracted values
     */
    private function extractWhereValues(array $instances): array
    {
        $values = [];

        foreach ($instances as $instance) {
            $sql = $instance['sql'] ?? '';

            // Try to extract value from WHERE clause
            if (preg_match('/WHERE\s+\w+\s*=\s*[\'"]?(\w+)[\'"]?/i', $sql, $matches)) {
                $values[] = $matches[1];
            } elseif (preg_match('/WHERE\s+\w+\s*=\s*(\d+)/i', $sql, $matches)) {
                $values[] = $matches[1];
            }
        }

        return array_unique($values);
    }

    /**
     * Analyze query performance and identify slow queries
     *
     * @param array<int, array<string, mixed>> $queries List of executed queries
     * @param float $threshold Threshold in milliseconds
     * @return array<int, array<string, mixed>> Slow queries
     */
    public function detectSlowQueries(array $queries, float $threshold = 100.0): array
    {
        $slowQueries = [];

        foreach ($queries as $query) {
            $time = $query['time'] ?? 0;

            if ($time >= $threshold) {
                $slowQueries[] = [
                    'sql'        => $query['sql'] ?? '',
                    'time'       => round($time, 2),
                    'location'   => $this->extractLocation($query),
                    'suggestion' => $this->generateSlowQuerySuggestion($query),
                ];
            }
        }

        // Sort by time (slowest first)
        usort($slowQueries, fn(array $a, array $b): int => $b['time'] <=> $a['time']);

        return $slowQueries;
    }

    /**
     * Generate suggestion for slow query optimization
     *
     * @param array<string, mixed> $query Query data
     * @return string Optimization suggestion
     */
    private function generateSlowQuerySuggestion(array $query): string
    {
        $sql = strtoupper($query['sql'] ?? '');

        $suggestions = [];

        // Check for missing WHERE clause
        if (!str_contains($sql, 'WHERE') && str_contains($sql, 'SELECT')) {
            $suggestions[] = "Consider adding a WHERE clause to limit results";
        }

        // Check for SELECT *
        if (str_contains($sql, 'SELECT *')) {
            $suggestions[] = "Select only needed columns instead of SELECT *";
        }

        // Check for missing indexes (heuristic)
        if (str_contains($sql, 'LIKE')) {
            $suggestions[] = "LIKE queries may benefit from full-text indexes";
        }

        // Check for ORDER BY without LIMIT
        if (str_contains($sql, 'ORDER BY') && !str_contains($sql, 'LIMIT')) {
            $suggestions[] = "Add LIMIT clause when using ORDER BY on large tables";
        }

        if ($suggestions === []) {
            $suggestions[] = "Review query execution plan and consider adding indexes";
        }

        return implode(". ", $suggestions);
    }

    /**
     * Get query statistics
     *
     * @param array<int, array<string, mixed>> $queries List of executed queries
     * @return array<string, mixed> Query statistics
     */
    public function getStatistics(array $queries): array
    {
        $totalTime = array_sum(array_column($queries, 'time'));
        $count     = count($queries);

        $times = array_column($queries, 'time');

        return [
            'total_count' => $count,
            'total_time'  => round($totalTime, 2),
            'avg_time'    => $count > 0 ? round($totalTime / $count, 2) : 0,
            'slowest'     => $times === [] ? 0 : max($times),
            'fastest'     => $times === [] ? 0 : min($times),
        ];
    }
}
