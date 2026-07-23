<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Analyzers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Analyzers\PerformanceAnalyzer;

/**
 * Test PerformanceAnalyzer alerts and analysis
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Test classes need one public method per test case
 * @phpstan-ignore-next-line PHPMD annotation syntax not recognized by PHPStan
 */
class PerformanceAnalyzerTest extends TestCase
{
    public function testDetectsSlowRequest(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_request', $alerts[0]['type']);
        $this->assertEquals('critical', $alerts[0]['level']);
    }

    public function testDetectsHighMemoryUsage(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 60], // 60 MB
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('high_memory', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testDetectsExcessiveQueries(): void
    {
        $queries = array_fill(0, 60, ['sql' => 'SELECT 1', 'time' => 1]);

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => $queries],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('excessive_queries', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testDetectsSlowQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 300],
            ['sql' => 'SELECT 2', 'time' => 300],
        ];

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => $queries],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_queries', $alerts[0]['type']);
    }

    public function testDetectsExcessiveHttpRequests(): void
    {
        $requests = array_fill(0, 15, ['method' => 'GET', 'url' => 'https://api.example.com', 'time' => 10]);

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => $requests],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('excessive_http', $alerts[0]['type']);
    }

    public function testDetectsSlowHttpRequests(): void
    {
        $requests = [
            ['method' => 'GET', 'url' => 'https://slow-api.example.com', 'time' => 600],
            ['method' => 'GET', 'url' => 'https://slow-api2.example.com', 'time' => 600],
        ];

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => $requests],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_http', $alerts[0]['type']);
    }

    public function testDetectsLowCacheHitRate(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 40, 'count' => 10], // Must have operations
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('low_cache_hit_rate', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testNoAlertWhenNoCacheOperations(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 0, 'count' => 0], // No operations
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        // Should have no cache-related alerts
        $cacheAlerts = array_filter($alerts, fn(array $a): bool => $a['type'] === 'low_cache_hit_rate');
        $this->assertEmpty($cacheAlerts);
    }

    public function testSortsByCriticalFirst(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        // Should have 2 alerts (slow request + high memory)
        $this->assertCount(2, $alerts);

        // First should be critical (slow request)
        $this->assertEquals('critical', $alerts[0]['level']);

        // Second should be warning (high memory)
        $this->assertEquals('warning', $alerts[1]['level']);
    }

    public function testNoAlertsForGoodPerformance(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => [['sql' => 'SELECT 1', 'time' => 5]]],
            'http'    => ['requests' => [['method' => 'GET', 'url' => 'https://api.example.com', 'time' => 50]]],
            'cache'   => ['hit_rate' => 90],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertEmpty($alerts);
    }

    public function testGetSummary(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 40, 'count' => 10],
        ];

        $summary = PerformanceAnalyzer::getSummary($data);

        $this->assertEquals(3, $summary['total_alerts']);
        $this->assertEquals(1, $summary['critical_count']);
        $this->assertEquals(2, $summary['warning_count']);
        $this->assertEquals(0, $summary['info_count']);
        $this->assertTrue($summary['has_issues']);
    }

    public function testHasIssues(): void
    {
        $dataWithIssues = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $dataWithoutIssues = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $this->assertTrue(PerformanceAnalyzer::hasIssues($dataWithIssues));
        $this->assertFalse(PerformanceAnalyzer::hasIssues($dataWithoutIssues));
    }

    public function testAlertsContainRequiredFields(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertNotEmpty($alerts);

        foreach ($alerts as $alert) {
            $this->assertArrayHasKey('level', $alert);
            $this->assertArrayHasKey('type', $alert);
            $this->assertArrayHasKey('icon', $alert);
            $this->assertArrayHasKey('message', $alert);
            $this->assertArrayHasKey('threshold', $alert);
            $this->assertArrayHasKey('actual', $alert);
            $this->assertArrayHasKey('action', $alert);
        }
    }

    public function testCustomThresholdsOverrideDefaults(): void
    {
        $data = [
            'request' => ['execution_time' => 600, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        // No alert with defaults (threshold: 1000ms)
        $this->assertEmpty(PerformanceAnalyzer::analyze($data));

        // Alert with lower threshold (500ms)
        $alerts = PerformanceAnalyzer::analyze($data, ['time_ms' => 500]);
        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_request', $alerts[0]['type']);
        $this->assertStringContainsString('500ms', $alerts[0]['threshold']);
    }

    public function testPartialThresholdOverride(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        // Override only time threshold to much higher value — memory alert should still fire
        $alerts = PerformanceAnalyzer::analyze($data, ['time_ms' => 5000]);

        $types = array_column($alerts, 'type');
        $this->assertNotContains('slow_request', $types);
        $this->assertContains('high_memory', $types);
    }

    public function testGetDefaultThresholds(): void
    {
        $defaults = PerformanceAnalyzer::getDefaultThresholds();

        $this->assertArrayHasKey('time_ms', $defaults);
        $this->assertArrayHasKey('memory_mb', $defaults);
        $this->assertArrayHasKey('query_count', $defaults);
        $this->assertArrayHasKey('query_time_ms', $defaults);
        $this->assertArrayHasKey('http_count', $defaults);
        $this->assertArrayHasKey('http_time_ms', $defaults);
        $this->assertEquals(1000, $defaults['time_ms']);
    }

    public function testCustomThresholdsPassedToSummary(): void
    {
        $data = [
            'request' => ['execution_time' => 600, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        // No issues with defaults
        $this->assertFalse(PerformanceAnalyzer::hasIssues($data));

        // Issues with custom threshold
        $this->assertTrue(PerformanceAnalyzer::hasIssues($data, ['time_ms' => 500]));

        $summary = PerformanceAnalyzer::getSummary($data, ['time_ms' => 500]);
        $this->assertEquals(1, $summary['total_alerts']);
        $this->assertTrue($summary['has_issues']);
    }

    /**
     * Build collector data with all metrics benign by default, overriding only
     * what a boundary case needs.
     *
     * @param array<string, mixed>            $request
     * @param array<int, array<string, mixed>> $queries
     * @param array<int, array<string, mixed>> $http
     * @param array<string, mixed>            $cache
     * @return array<string, mixed>
     */
    private static function data(array $request = [], array $queries = [], array $http = [], array $cache = []): array
    {
        return [
            'request' => array_merge(['execution_time' => 0, 'memory_peak' => 0], $request),
            'queries' => ['queries' => $queries],
            'http'    => ['requests' => $http],
            'cache'   => array_merge(['hit_rate' => 100, 'count' => 0], $cache),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function items(int $count, float $time = 0.0): array
    {
        return array_fill(0, $count, ['time' => $time]);
    }

    /**
     * Exact threshold boundaries: a metric that equals its threshold must NOT
     * trip the higher band, and the warning band must start exactly at the
     * multiplier. Kills the GreaterThan (> vs >=) and Multiplication (* vs /)
     * mutants across every analyzer.
     *
     * @param array<string, mixed> $data
     */
    #[DataProvider('boundaryProvider')]
    public function testThresholdBoundaries(array $data, ?string $type, ?string $level): void
    {
        $alerts = PerformanceAnalyzer::analyze($data);

        if ($type === null) {
            $this->assertSame([], $alerts);

            return;
        }

        $this->assertCount(1, $alerts);
        $this->assertSame($type, $alerts[0]['type']);
        $this->assertSame($level, $alerts[0]['level']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: ?string, 2: ?string}>
     */
    public static function boundaryProvider(): array
    {
        return [
            // Execution time: critical > 1000, warning > 700.
            'time == 1000 -> warning'      => [self::data(['execution_time' => 1000]), 'slow_request', 'warning'],
            'time 1001 -> critical'        => [self::data(['execution_time' => 1001]), 'slow_request', 'critical'],
            'time == 700 -> none'          => [self::data(['execution_time' => 700]), null, null],
            'time 701 -> warning'          => [self::data(['execution_time' => 701]), 'slow_request', 'warning'],

            // Memory: warning > 50, info > 40 (0.8 * 50).
            'mem == 50 -> info'            => [self::data(['memory_peak' => 50]), 'high_memory', 'info'],
            'mem 51 -> warning'            => [self::data(['memory_peak' => 51]), 'high_memory', 'warning'],
            'mem == 40 -> none'            => [self::data(['memory_peak' => 40]), null, null],
            'mem 41 -> info'               => [self::data(['memory_peak' => 41]), 'high_memory', 'info'],

            // Query count: warning > 50.
            'queries == 50 -> none'        => [self::data(queries: self::items(50)), null, null],
            'queries 51 -> warning'        => [self::data(queries: self::items(51)), 'excessive_queries', 'warning'],

            // Query time: warning > 500.
            'query time == 500 -> none'    => [self::data(queries: self::items(2, 250)), null, null],
            'query time 501 -> warning'    => [self::data(queries: self::items(2, 250.5)), 'slow_queries', 'warning'],

            // HTTP count: warning > 10.
            'http == 10 -> none'           => [self::data(http: self::items(10)), null, null],
            'http 11 -> warning'           => [self::data(http: self::items(11)), 'excessive_http', 'warning'],

            // HTTP time: warning > 1000.
            'http time == 1000 -> none'    => [self::data(http: self::items(2, 500)), null, null],
            'http time 1001 -> warning'    => [self::data(http: self::items(2, 500.5)), 'slow_http', 'warning'],

            // Cache hit rate: warning < 50, info < 70 (only when count > 0).
            'hit 49 -> warning'            => [self::data(cache: ['hit_rate' => 49, 'count' => 5]), 'low_cache_hit_rate', 'warning'],
            'hit == 50 -> info'            => [self::data(cache: ['hit_rate' => 50, 'count' => 5]), 'low_cache_hit_rate', 'info'],
            'hit 69 -> info'               => [self::data(cache: ['hit_rate' => 69, 'count' => 5]), 'low_cache_hit_rate', 'info'],
            'hit == 70 -> none'            => [self::data(cache: ['hit_rate' => 70, 'count' => 5]), null, null],
            'low hit but count 0 -> none'  => [self::data(cache: ['hit_rate' => 10, 'count' => 0]), null, null],
        ];
    }

    /**
     * Exact alert text: message, threshold and actual. Kills the Concat,
     * ConcatOperandRemoval, RoundingFamily and sprintf mutants that leave the
     * numbers/units subtly wrong.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $expected
     */
    #[DataProvider('contentProvider')]
    public function testAlertContent(array $data, array $expected): void
    {
        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertSame($expected['message'], $alerts[0]['message']);
        $this->assertSame($expected['threshold'], $alerts[0]['threshold']);
        $this->assertSame($expected['actual'], $alerts[0]['actual']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    public static function contentProvider(): array
    {
        return [
            'slow request' => [
                self::data(['execution_time' => 1500]),
                ['message' => 'Slow Request (1500ms)', 'threshold' => '1000ms', 'actual' => '1500ms'],
            ],
            'approaching slow request' => [
                self::data(['execution_time' => 800]),
                ['message' => 'Approaching Slow Request (800ms)', 'threshold' => '1000ms', 'actual' => '800ms'],
            ],
            'high memory' => [
                self::data(['memory_peak' => 60.5]),
                ['message' => 'High Memory Usage (60.5MB)', 'threshold' => '50MB', 'actual' => '60.5MB'],
            ],
            'elevated memory (info band)' => [
                self::data(['memory_peak' => 45.5]),
                ['message' => 'Elevated Memory Usage (45.5MB)', 'threshold' => '50MB', 'actual' => '45.5MB'],
            ],
            'excessive queries' => [
                self::data(queries: self::items(60)),
                ['message' => 'Excessive Queries (60)', 'threshold' => '50 queries', 'actual' => '60 queries'],
            ],
            'slow queries' => [
                self::data(queries: self::items(2, 300)),
                ['message' => 'Slow Database Queries (600ms total)', 'threshold' => '500ms', 'actual' => '600ms'],
            ],
            'excessive http' => [
                self::data(http: self::items(15)),
                ['message' => 'Excessive HTTP Requests (15)', 'threshold' => '10 requests', 'actual' => '15 requests'],
            ],
            'slow http' => [
                self::data(http: self::items(2, 600)),
                ['message' => 'Slow HTTP Requests (1200ms total)', 'threshold' => '1000ms', 'actual' => '1200ms'],
            ],
            'low cache hit rate' => [
                self::data(cache: ['hit_rate' => 40.5, 'count' => 5]),
                ['message' => 'Low Cache Hit Rate (40.5%)', 'threshold' => '70%', 'actual' => '40.5%'],
            ],
            'moderate cache hit rate' => [
                self::data(cache: ['hit_rate' => 60.5, 'count' => 5]),
                ['message' => 'Moderate Cache Hit Rate (60.5%)', 'threshold' => '70%', 'actual' => '60.5%'],
            ],
        ];
    }

    /**
     * Every analyzer contributes and the result is sorted critical-first.
     * Kills the UnwrapArrayMerge mutants (a dropped array_merge would lose an
     * analyzer's alert) and the usort FunctionCallRemoval.
     */
    public function testAllAnalyzersContributeInCriticalFirstOrder(): void
    {
        $data = self::data(
            ['execution_time' => 1500, 'memory_peak' => 60],   // critical + warning
            self::items(60),                                    // warning (excessive queries)
            self::items(15),                                    // warning (excessive http)
            ['hit_rate' => 40, 'count' => 5],                   // warning (low cache)
        );

        $alerts = PerformanceAnalyzer::analyze($data);
        $types  = array_column($alerts, 'type');
        $levels = array_column($alerts, 'level');

        // One alert from each analyzer branch that tripped.
        $this->assertContains('slow_request', $types);
        $this->assertContains('high_memory', $types);
        $this->assertContains('excessive_queries', $types);
        $this->assertContains('excessive_http', $types);
        $this->assertContains('low_cache_hit_rate', $types);

        // Critical must sort before every warning.
        $this->assertSame('critical', $levels[0]);
        $this->assertSame('slow_request', $types[0]);
        foreach (array_slice($levels, 1) as $level) {
            $this->assertSame('warning', $level);
        }
    }

    /**
     * Critical, warning and info in one result must sort in that exact order.
     * Kills the compareLevels order-map mutants (the 0/1/2 ranks).
     */
    public function testLevelsSortCriticalWarningInfo(): void
    {
        $data = self::data(
            ['execution_time' => 1500, 'memory_peak' => 45.5], // critical + info
            self::items(60),                                     // warning
        );

        $levels = array_column(PerformanceAnalyzer::analyze($data), 'level');

        $this->assertSame(['critical', 'warning', 'info'], $levels);
    }
}
