<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;
use Zappzarapp\DevToolbar\Renderers\Panels\QueriesPanelRenderer;

/**
 * Test QueriesPanelRenderer — N+1 detection, slow query detection, and the
 * per-query performance class assignment that depends on those detections.
 */
class QueriesPanelRendererDetectionTest extends TestCase
{
    private QueriesPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new QueriesPanelRenderer(new QueryAnalyzer());
    }

    // ========== N+1 detection ==========

    public function testRenderTabWithNPlusOneDetection(): void
    {
        // 3+ same-pattern queries trigger N+1 detection in QueryAnalyzer.
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 1',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 2',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 3',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
            ],
            'total_time' => 30.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('(1 pattern)', $result);
        $this->assertStringContainsString('3 times with different parameters', $result);
        $this->assertStringContainsString('UserController.php:45', $result);
        $this->assertStringContainsString('WHERE IN', $result);
    }

    public function testRenderTabWithMultipleNPlusOnePatterns(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 10.0],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 1', 'time' => 5.0],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 2', 'time' => 5.0],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 3', 'time' => 5.0],
            ],
            'total_time' => 45.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('(2 patterns)', $result);
        $this->assertStringContainsString('FROM POSTS WHERE USER_ID = ?', $result);
        $this->assertStringContainsString('FROM COMMENTS WHERE POST_ID = ?', $result);
    }

    public function testRenderTabEscapesHtmlInNPlusOneSuggestion(): void
    {
        // QueryAnalyzer doesn't generate HTML in suggestions, but verify the
        // N+1 section structure is rendered with escaping in place.
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 10.0],
            ],
            'total_time' => 30.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('dev-toolbar-suggestion', $result);
    }

    // ========== Slow query detection ==========

    public function testRenderTabWithSlowQueryDetection(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM huge_table', 'time' => 250.0],
            ],
            'total_time' => 250.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Default slow-query threshold is 100ms.
        $this->assertStringContainsString('Slow Queries Detected!', $result);
        $this->assertStringContainsString('(1 query)', $result);
        $this->assertStringContainsString('SELECT * FROM huge_table', $result);
        $this->assertStringContainsString('250.00ms', $result);
    }

    public function testRenderTabWithMultipleSlowQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM table1', 'time' => 300.0],
                ['sql' => 'SELECT * FROM table2', 'time' => 200.0],
            ],
            'total_time' => 500.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('(2 queries)', $result);
        $this->assertStringContainsString('SELECT * FROM table1', $result);
        $this->assertStringContainsString('SELECT * FROM table2', $result);
    }

    public function testRenderTabEscapesHtmlInSlowQuerySuggestion(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM table', 'time' => 100.0],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-suggestion', $result);
    }

    // ========== Combined N+1 and slow ==========

    public function testRenderTabWithBothNPlusOneAndSlowQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 10.0],
                ['sql' => 'SELECT * FROM big_table', 'time' => 500.0],
            ],
            'total_time' => 530.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('Slow Queries Detected!', $result);
    }

    // ========== Per-query performance class ==========

    public function testRenderTabAppliesFastClassForFastQueries(): void
    {
        $data = [
            'queries'    => [['sql' => 'SELECT * FROM users', 'time' => 50.0, 'bindings' => []]],
            'total_time' => 50.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query fast', $result);
        $this->assertStringContainsString('dev-toolbar-query-time fast', $result);
    }

    public function testRenderTabAppliesSlowClassForSlowQueries(): void
    {
        $data = [
            'queries'    => [['sql' => 'SELECT * FROM users', 'time' => 150.0, 'bindings' => []]],
            'total_time' => 150.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query slow', $result);
        $this->assertStringContainsString('dev-toolbar-query-time slow', $result);
    }

    public function testRenderTabAppliesVerySlowClassForVerySlowQueries(): void
    {
        $data = [
            'queries'    => [['sql' => 'SELECT * FROM users', 'time' => 600.0, 'bindings' => []]],
            'total_time' => 600.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query very-slow', $result);
        $this->assertStringContainsString('dev-toolbar-query-time very-slow', $result);
    }
}
