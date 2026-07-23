<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;
use Zappzarapp\DevToolbar\Renderers\Panels\QueriesPanelRenderer;

/**
 * Test QueriesPanelRenderer
 */
class QueriesPanelRendererTest extends TestCase
{
    private QueriesPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new QueriesPanelRenderer(new QueryAnalyzer());
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('queries', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests - Empty states ==========

    public function testRenderTabWithNoQueries(): void
    {
        $data = [];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    public function testRenderTabWithEmptyQueriesArray(): void
    {
        $data = ['queries' => []];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    // ========== renderTab() tests - Single query ==========

    public function testRenderTabWithSingleQuery(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE id = 1',
                    'time'     => 15.5,
                    'bindings' => [],
                ],
            ],
            'total_time' => 15.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 1 query in 15.50ms', $result);
        $this->assertStringContainsString('avg: 15.50ms', $result);
        $this->assertStringContainsString('SELECT * FROM users WHERE id = 1', $result);
        $this->assertStringContainsString('15.50ms', $result);
    }

    // ========== renderTab() tests - Multiple queries ==========

    public function testRenderTabWithMultipleQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0, 'bindings' => []],
                ['sql' => 'SELECT * FROM posts', 'time' => 20.0, 'bindings' => []],
                ['sql' => 'SELECT * FROM comments', 'time' => 30.0, 'bindings' => []],
            ],
            'total_time' => 60.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 3 queries in 60.00ms', $result);
        $this->assertStringContainsString('avg: 20.00ms', $result);
        $this->assertStringContainsString('SELECT * FROM users', $result);
        $this->assertStringContainsString('SELECT * FROM posts', $result);
        $this->assertStringContainsString('SELECT * FROM comments', $result);
    }

    // ========== renderTab() tests - Query with bindings ==========

    public function testRenderTabWithQueryBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE id = ? AND name = ?',
                    'time'     => 12.3,
                    'bindings' => [1, 'John'],
                ],
            ],
            'total_time' => 12.3,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('SELECT * FROM users WHERE id = ? AND name = ?', $result);
        $this->assertStringContainsString('Bindings:', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('John', $result);
    }

    public function testRenderTabWithComplexBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'INSERT INTO users',
                    'time'     => 5.0,
                    'bindings' => [
                        'name'  => 'Alice',
                        'email' => 'alice@example.com',
                        'age'   => 30,
                    ],
                ],
            ],
            'total_time' => 5.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Bindings:', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@example.com', $result);
    }

    // ========== renderTab() tests - HTML escaping ==========

    public function testRenderTabEscapesHtmlInSql(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE name = "<script>alert(1)</script>"',
                    'time'     => 10.0,
                    'bindings' => [],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString(htmlspecialchars('<script>'), $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'INSERT INTO users',
                    'time'     => 5.0,
                    'bindings' => ['name' => '<script>alert(1)</script>'],
                ],
            ],
            'total_time' => 5.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString(htmlspecialchars('<script>'), $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    // ========== renderTab() tests - Backtrace ==========

    public function testRenderTabWithBacktrace(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM users',
                    'time'      => 10.0,
                    'bindings'  => [],
                    'backtrace' => [
                        [
                            'file'     => '/path/to/UserController.php',
                            'line'     => 45,
                            'function' => 'getUsers',
                        ],
                        [
                            'file'     => '/path/to/Router.php',
                            'line'     => 100,
                            'function' => 'dispatch',
                        ],
                    ],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Called from:', $result);
        $this->assertStringContainsString('/path/to/UserController.php:45', $result);
        $this->assertStringContainsString('getUsers()', $result);
        $this->assertStringContainsString('/path/to/Router.php:100', $result);
        $this->assertStringContainsString('dispatch()', $result);
    }

    public function testRenderTabWithEmptyBacktrace(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM users',
                    'time'      => 10.0,
                    'bindings'  => [],
                    'backtrace' => [],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should not render backtrace section if empty
        $this->assertStringNotContainsString('Called from:', $result);
    }


    // ========== renderTab() tests - Expected structure ==========

    public function testRenderTabContainsExpectedSections(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0, 'bindings' => []],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-section-title', $result);
        $this->assertStringContainsString('Query List:', $result);
        $this->assertStringContainsString('dev-toolbar-query', $result);
        $this->assertStringContainsString('dev-toolbar-query-header', $result);
        $this->assertStringContainsString('dev-toolbar-query-time', $result);
        $this->assertStringContainsString('dev-toolbar-query-sql', $result);
    }

    // ========== renderTab() tests - Edge cases ==========

    public function testRenderTabHandlesMissingTotalTime(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 1 query in 0.00ms', $result);
    }

    public function testRenderTabHandlesMissingQueryTime(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users'],
            ],
            'total_time' => 0.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('0.00ms', $result);
    }

    public function testRenderTabHandlesMissingSqlField(): void
    {
        $data = [
            'queries' => [
                ['time' => 10.0, 'bindings' => []],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should not crash, should render with empty SQL
        $this->assertStringContainsString('dev-toolbar-query-sql', $result);
    }

    public function testRenderTabWithZeroQueries(): void
    {
        $data = [
            'queries'    => [],
            'total_time' => 0.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    public function testRenderTabCalculatesAverageTimeCorrectly(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT 1', 'time' => 10.0],
                ['sql' => 'SELECT 2', 'time' => 20.0],
                ['sql' => 'SELECT 3', 'time' => 30.0],
            ],
            'total_time' => 60.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('avg: 20.00ms', $result);
    }
}
