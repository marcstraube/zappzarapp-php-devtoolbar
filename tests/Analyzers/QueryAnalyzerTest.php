<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Analyzers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;

/**
 * Test QueryAnalyzer N+1 detection and query analysis
 */
class QueryAnalyzerTest extends TestCase
{
    private QueryAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new QueryAnalyzer();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function repeat(string $sql, int $count, float $time = 10.0): array
    {
        return array_fill(0, $count, ['sql' => $sql, 'time' => $time]);
    }

    public function testDetectsNPlusOnePattern(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 11],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 12],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals(3, $nPlusOnes[0]['count']);
        $this->assertEquals(33, $nPlusOnes[0]['total_time']);
        $this->assertEquals(11, $nPlusOnes[0]['avg_time']);
    }

    public function testDoesNotFlagDifferentQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE id = 1', 'time' => 11],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertCount(0, $nPlusOnes);
    }

    public function testRequiresMinimumThreeOccurrences(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        // Only 2 occurrences, not flagged
        $this->assertCount(0, $nPlusOnes);
    }

    public function testNormalizesQueriesCorrectly(): void
    {
        $queries = [
            ['sql' => "SELECT * FROM users WHERE id = 1 AND name = 'John'", 'time' => 10],
            ['sql' => "SELECT * FROM users WHERE id = 2 AND name = 'Jane'", 'time' => 10],
            ['sql' => "SELECT * FROM users WHERE id = 3 AND name = 'Bob'", 'time' => 10],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        // Should detect as N+1 despite different values
        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals(3, $nPlusOnes[0]['count']);
    }

    public function testSortsByTotalTime(): void
    {
        $queries = [
            // Pattern 1: Fast queries
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 5],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 5],
            ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 5],

            // Pattern 2: Slow queries
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 50],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 50],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 50],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertCount(2, $nPlusOnes);

        // First should be the slower pattern (posts)
        $this->assertEquals(150, $nPlusOnes[0]['total_time']);
        // Second should be the faster pattern (users)
        $this->assertEquals(15, $nPlusOnes[1]['total_time']);
    }

    public function testExtractsLocationFromBacktrace(): void
    {
        $queries = [
            [
                'sql'       => 'SELECT * FROM users WHERE id = 1',
                'time'      => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
            [
                'sql'       => 'SELECT * FROM users WHERE id = 2',
                'time'      => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
            [
                'sql'       => 'SELECT * FROM users WHERE id = 3',
                'time'      => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals('UserRepository.php:45', $nPlusOnes[0]['location']);
    }

    public function testGeneratesSuggestion(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 10],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertArrayHasKey('suggestion', $nPlusOnes[0]);
        $this->assertNotEmpty($nPlusOnes[0]['suggestion']);
        $this->assertStringContainsString('WHERE IN', $nPlusOnes[0]['suggestion']);
    }

    public function testDetectsSlowQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users', 'time' => 150],
            ['sql' => 'SELECT * FROM posts', 'time' => 50],
        ];

        $slowQueries = $this->analyzer->detectSlowQueries($queries, 100);

        $this->assertCount(1, $slowQueries);
        $this->assertEquals('SELECT * FROM users', $slowQueries[0]['sql']);
        $this->assertEquals(150, $slowQueries[0]['time']);
    }

    public function testSlowQueriesSortedByTime(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 200],
            ['sql' => 'SELECT 2', 'time' => 500],
            ['sql' => 'SELECT 3', 'time' => 300],
        ];

        $slowQueries = $this->analyzer->detectSlowQueries($queries, 100);

        $this->assertCount(3, $slowQueries);
        $this->assertEquals(500, $slowQueries[0]['time']); // Slowest first
        $this->assertEquals(300, $slowQueries[1]['time']);
        $this->assertEquals(200, $slowQueries[2]['time']);
    }

    public function testGeneratesSlowQuerySuggestions(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users', 'time' => 150], // SELECT *
            ['sql' => 'SELECT name FROM posts WHERE title LIKE "%test%"', 'time' => 200], // LIKE
        ];

        $slowQueries = $this->analyzer->detectSlowQueries($queries, 100);

        $this->assertCount(2, $slowQueries);

        // detectSlowQueries() sorts by time descending: LIKE query (200ms) is [0], SELECT * (150ms) is [1]
        $this->assertStringContainsString('LIKE', $slowQueries[0]['suggestion']);
        $this->assertStringContainsString('SELECT *', $slowQueries[1]['suggestion']);
    }

    public function testGetStatistics(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 10],
            ['sql' => 'SELECT 2', 'time' => 20],
            ['sql' => 'SELECT 3', 'time' => 30],
        ];

        $stats = $this->analyzer->getStatistics($queries);

        $this->assertEquals(3, $stats['total_count']);
        $this->assertEquals(60, $stats['total_time']);
        $this->assertEquals(20, $stats['avg_time']);
        $this->assertEquals(30, $stats['slowest']);
        $this->assertEquals(10, $stats['fastest']);
    }

    public function testGetStatisticsWithEmptyQueries(): void
    {
        $stats = $this->analyzer->getStatistics([]);

        $this->assertEquals(0, $stats['total_count']);
        $this->assertEquals(0, $stats['total_time']);
        $this->assertEquals(0, $stats['avg_time']);
        $this->assertEquals(0, $stats['slowest']);
        $this->assertEquals(0, $stats['fastest']);
    }

    public function testNPlusOneSuggestionSpellsOutTableColumnAndValues(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 10],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertSame(
            "Use a WHERE IN clause to fetch all records in a single query:\n"
            . 'SELECT * FROM POSTS WHERE USER_ID IN (1, 2, 3)',
            $nPlusOnes[0]['suggestion']
        );
    }

    public function testGenericSuggestionWhenNoWhereEqualsPattern(): void
    {
        $nPlusOnes = $this->analyzer->detectNPlusOne($this->repeat('SELECT * FROM logs', 3));

        $this->assertSame(
            "Consider using a JOIN or eager loading to reduce 3 queries to 1 query.\n"
            . 'Look for opportunities to batch these queries together.',
            $nPlusOnes[0]['suggestion']
        );
    }

    public function testNPlusOneRoundsTotalAndAverageToTwoDecimals(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM t WHERE id = 1', 'time' => 1.111],
            ['sql' => 'SELECT * FROM t WHERE id = 2', 'time' => 2.222],
            ['sql' => 'SELECT * FROM t WHERE id = 3', 'time' => 3.333],
        ];

        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        $this->assertEquals(6.67, $nPlusOnes[0]['total_time']);
        $this->assertEquals(2.22, $nPlusOnes[0]['avg_time']);
    }

    public function testSlowQueryThresholdIsInclusive(): void
    {
        // Exactly at the threshold is slow; just below is not.
        $this->assertCount(1, $this->analyzer->detectSlowQueries([['sql' => 'SELECT 1', 'time' => 100]], 100));
        $this->assertCount(0, $this->analyzer->detectSlowQueries([['sql' => 'SELECT 1', 'time' => 99.99]], 100));
    }

    public function testSlowQueryTimeRoundedToTwoDecimals(): void
    {
        $slow = $this->analyzer->detectSlowQueries([['sql' => 'SELECT 1', 'time' => 123.456]], 100);

        $this->assertEquals(123.46, $slow[0]['time']);
    }

    /**
     * Each slow-query heuristic branch produces its own suggestion, and
     * multiple applicable ones are joined with ". ".
     */
    #[DataProvider('slowQuerySuggestionProvider')]
    public function testSlowQuerySuggestionHeuristics(string $sql, string $expected): void
    {
        $slow = $this->analyzer->detectSlowQueries([['sql' => $sql, 'time' => 500]], 100);

        $this->assertSame($expected, $slow[0]['suggestion']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function slowQuerySuggestionProvider(): array
    {
        return [
            'missing where'              => ['SELECT id FROM users', 'Consider adding a WHERE clause to limit results'],
            'select star'                => ['SELECT * FROM users WHERE id = 1', 'Select only needed columns instead of SELECT *'],
            'like'                       => ['SELECT id FROM users WHERE name LIKE "%x%"', 'LIKE queries may benefit from full-text indexes'],
            'order by without limit'     => ['SELECT id FROM users WHERE id = 1 ORDER BY name', 'Add LIMIT clause when using ORDER BY on large tables'],
            'nothing flagged -> default' => ['UPDATE users SET x = 1 WHERE id = 1', 'Review query execution plan and consider adding indexes'],
            'two heuristics joined'      => [
                'SELECT * FROM users',
                'Consider adding a WHERE clause to limit results. Select only needed columns instead of SELECT *',
            ],
        ];
    }

    public function testGetStatisticsRoundsAndPicksExtremes(): void
    {
        $stats = $this->analyzer->getStatistics([
            ['sql' => 'a', 'time' => 1.111],
            ['sql' => 'b', 'time' => 2.222],
            ['sql' => 'c', 'time' => 3.333],
        ]);

        $this->assertEquals(6.67, $stats['total_time']);
        $this->assertEquals(2.22, $stats['avg_time']);
        $this->assertEquals(3.333, $stats['slowest']);
        $this->assertEquals(1.111, $stats['fastest']);
    }
}
