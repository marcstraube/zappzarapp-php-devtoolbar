<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Config\MiniBarConfig;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\Renderers\MiniBarRenderer;

/**
 * Test MiniBarRenderer including alert badge rendering
 */
class MiniBarRendererTest extends TestCase
{
    /**
     * Create a mock collector returning the given data
     *
     * @param array<string, mixed> $data
     */
    private function mockCollector(array $data): CollectorInterface
    {
        $collector = $this->createStub(CollectorInterface::class);
        $collector->method('getData')->willReturn($data);

        return $collector;
    }

    /**
     * Build a standard set of collectors with customizable data
     *
     * @param array<string, mixed> $overrides Collector data overrides keyed by collector name
     * @return array<string, CollectorInterface>
     */
    private function buildCollectors(array $overrides = []): array
    {
        $defaults = [
            'request' => [
                'execution_time' => 100,
                'memory_peak'    => 9.5,
                'method'         => 'GET',
                'uri'            => '/test',
                'count'          => 0,
            ],
            'queries' => [
                'queries'    => [],
                'count'      => 0,
                'total_time' => 0,
            ],
            'http' => [
                'requests'   => [],
                'count'      => 0,
                'total_time' => 0,
            ],
            'cache' => [
                'operations' => [],
                'hits'       => 0,
                'misses'     => 0,
                'hit_rate'   => 100,
                'total_time' => 0,
                'count'      => 0,
            ],
            'messages'   => ['messages' => [], 'count' => 0],
            'exceptions' => ['exceptions' => [], 'count' => 0],
            'timeline'   => ['timeline' => [], 'total_time' => 0, 'count' => 0],
            'history'    => ['count' => 0],
        ];

        $collectors = [];
        foreach ($defaults as $name => $data) {
            $collectors[$name] = $this->mockCollector(
                array_merge($data, $overrides[$name] ?? [])
            );
        }

        return $collectors;
    }

    public function testRendersMiniBar(): void
    {
        $renderer = new MiniBarRenderer($this->buildCollectors(), MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('dev-toolbar-mini', $output);
        $this->assertStringContainsString('100ms', $output);
        $this->assertStringContainsString('9.5MB', $output);
        $this->assertStringContainsString('0 queries', $output);
    }

    public function testNoAlertBadgeWhenPerformanceIsGood(): void
    {
        $renderer = new MiniBarRenderer($this->buildCollectors(), MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringNotContainsString('dev-toolbar-mini-alert', $output);
    }

    public function testShowsCriticalAlertForSlowRequest(): void
    {
        $collectors = $this->buildCollectors([
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('dev-toolbar-mini-alert', $output);
        $this->assertStringContainsString('alert-critical', $output);
    }

    public function testShowsWarningAlertForHighMemory(): void
    {
        $collectors = $this->buildCollectors([
            'request' => ['execution_time' => 100, 'memory_peak' => 60],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('dev-toolbar-mini-alert', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    public function testShowsWarningAlertForExcessiveQueries(): void
    {
        $queries    = array_fill(0, 60, ['sql' => 'SELECT 1', 'time' => 1]);
        $collectors = $this->buildCollectors([
            'queries' => ['queries' => $queries, 'count' => 60, 'total_time' => 60],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('dev-toolbar-mini-alert', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    public function testShowsAlertForNPlusOneQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 11],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 12],
        ];

        $collectors = $this->buildCollectors([
            'queries' => ['queries' => $queries, 'count' => 3, 'total_time' => 33],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('dev-toolbar-mini-alert', $output);
        $this->assertStringContainsString('alert-warning', $output);
    }

    public function testAlertBadgeShowsCorrectCount(): void
    {
        // Slow request (critical) + high memory (warning) = 2 alerts
        $collectors = $this->buildCollectors([
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('2 performance issues detected', $output);
        $this->assertMatchesRegularExpression('/⚠.*2/', $output);
    }

    public function testCriticalLevelTakesPrecedenceOverWarning(): void
    {
        // Critical (slow request) + warning (high memory)
        $collectors = $this->buildCollectors([
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('alert-critical', $output);
        $this->assertStringNotContainsString('alert-warning', $output);
    }

    public function testSingularIssueTextForOneAlert(): void
    {
        $collectors = $this->buildCollectors([
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
        ]);

        $renderer = new MiniBarRenderer($collectors, MiniBarConfig::default());
        $output   = $renderer->render();

        $this->assertStringContainsString('1 performance issue detected', $output);
        $this->assertStringNotContainsString('issues', $output);
    }
}
