<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\TimelinePanelRenderer;

/**
 * Test TimelinePanelRenderer
 */
class TimelinePanelRendererTest extends TestCase
{
    private TimelinePanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TimelinePanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('timeline', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        $this->assertStringContainsString('No timeline data available', $result);
    }

    public function testRenderTabWithEmptyTimeline(): void
    {
        $data = [
            'timeline'   => [],
            'total_time' => 0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No timeline data available', $result);
    }

    public function testRenderTabWithSingleEvent(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Database Query',
                    'duration'      => 45.5,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 91.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Request Timeline (Total: 91.00ms)', $result);
        $this->assertStringContainsString('Database Query', $result);
        $this->assertStringContainsString('45.50ms (50.0%)', $result);
        $this->assertStringContainsString('width: 50%', $result);
    }

    public function testRenderTabWithMultipleEvents(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Bootstrap',
                    'duration'      => 10.0,
                    'percentage'    => 10.0,
                    'is_bottleneck' => false,
                ],
                [
                    'label'         => 'Database Queries',
                    'duration'      => 70.0,
                    'percentage'    => 70.0,
                    'is_bottleneck' => true,
                ],
                [
                    'label'         => 'View Rendering',
                    'duration'      => 20.0,
                    'percentage'    => 20.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Request Timeline (Total: 100.00ms)', $result);
        $this->assertStringContainsString('Bootstrap', $result);
        $this->assertStringContainsString('Database Queries', $result);
        $this->assertStringContainsString('View Rendering', $result);
        $this->assertStringContainsString('10.00ms (10.0%)', $result);
        $this->assertStringContainsString('70.00ms (70.0%)', $result);
        $this->assertStringContainsString('20.00ms (20.0%)', $result);
    }

    public function testRenderTabWithBottleneck(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Slow Operation',
                    'duration'      => 500.0,
                    'percentage'    => 90.0,
                    'is_bottleneck' => true,
                ],
            ],
            'total_time' => 555.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('bottleneck', $result);
        $this->assertStringContainsString('Slow Operation', $result);
    }

    public function testRenderTabWithoutBottleneck(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Fast Operation',
                    'duration'      => 10.0,
                    'percentage'    => 10.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should have timeline-item but not bottleneck class
        $this->assertMatchesRegularExpression(
            '/<div class="dev-toolbar-timeline-item\s*" title="/',
            $result
        );
    }

    public function testRenderTabWithSubEvents(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Database Operations',
                    'duration'      => 100.0,
                    'percentage'    => 80.0,
                    'is_bottleneck' => true,
                    'events'        => [
                        [
                            'label'    => 'Query 1: SELECT * FROM users',
                            'duration' => 40.0,
                        ],
                        [
                            'label'    => 'Query 2: SELECT * FROM posts',
                            'duration' => 60.0,
                        ],
                    ],
                ],
            ],
            'total_time' => 125.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Database Operations', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-events', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-subevent', $result);
        $this->assertStringContainsString('├─ Query 1: SELECT * FROM users (40.00ms)', $result);
        $this->assertStringContainsString('├─ Query 2: SELECT * FROM posts (60.00ms)', $result);
    }

    public function testRenderTabWithoutSubEvents(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Simple Operation',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringNotContainsString('dev-toolbar-timeline-events', $result);
        $this->assertStringNotContainsString('dev-toolbar-timeline-subevent', $result);
    }

    public function testRenderTabWithEmptySubEvents(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Operation',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                    'events'        => [],
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringNotContainsString('dev-toolbar-timeline-events', $result);
    }

    public function testRenderTabWidthPercentageCappedAt100(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Over 100%',
                    'duration'      => 150.0,
                    'percentage'    => 150.0, // Over 100%
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Width should be capped at 100%
        $this->assertStringContainsString('width: 100%', $result);
        $this->assertStringNotContainsString('width: 150%', $result);
    }

    public function testRenderTabDurationCalculation(): void
    {
        $durations = [0.0, 1.5, 10.0, 100.5, 1000.0];

        foreach ($durations as $duration) {
            $data = [
                'timeline' => [
                    [
                        'label'         => 'Test',
                        'duration'      => $duration,
                        'percentage'    => 50.0,
                        'is_bottleneck' => false,
                    ],
                ],
                'total_time' => $duration * 2,
            ];

            $result = $this->renderer->renderTab($data);

            $this->assertStringContainsString(sprintf('%.2fms', $duration), $result);
        }
    }

    public function testRenderTabEscapesHtmlInLabel(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => '<script>alert("XSS")</script>',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInSubEventLabel(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Safe Label',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                    'events'        => [
                        [
                            'label'    => '<script>alert("XSS")</script>',
                            'duration' => 25.0,
                        ],
                    ],
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabContainsExpectedSections(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Test Operation',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-item', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-label', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-bar-container', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-bar', $result);
        $this->assertStringContainsString('dev-toolbar-timeline-time', $result);
        $this->assertStringContainsString('Request Timeline', $result);
    }

    public function testRenderTabHandlesMissingTimelineFields(): void
    {
        $data = [
            'timeline' => [
                [], // Empty timeline item
            ],
            'total_time' => 0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should render with defaults
        $this->assertStringContainsString('0.00ms (0.0%)', $result);
        $this->assertStringContainsString('width: 0%', $result);
    }

    public function testRenderTabHandlesMissingSubEventFields(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Parent',
                    'duration'      => 50.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => false,
                    'events'        => [
                        [], // Empty sub-event
                    ],
                ],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should render sub-event with defaults
        $this->assertStringContainsString('dev-toolbar-timeline-subevent', $result);
        $this->assertStringContainsString('0.00ms', $result);
    }

    public function testRenderTabZeroTotalTime(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Instant',
                    'duration'      => 0.0,
                    'percentage'    => 0.0,
                    'is_bottleneck' => false,
                ],
            ],
            'total_time' => 0.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Request Timeline (Total: 0.00ms)', $result);
    }

    public function testRenderTabMultipleItemsWithBottlenecks(): void
    {
        $data = [
            'timeline' => [
                [
                    'label'         => 'Fast Operation 1',
                    'duration'      => 10.0,
                    'percentage'    => 5.0,
                    'is_bottleneck' => false,
                ],
                [
                    'label'         => 'Bottleneck 1',
                    'duration'      => 100.0,
                    'percentage'    => 50.0,
                    'is_bottleneck' => true,
                ],
                [
                    'label'         => 'Fast Operation 2',
                    'duration'      => 10.0,
                    'percentage'    => 5.0,
                    'is_bottleneck' => false,
                ],
                [
                    'label'         => 'Bottleneck 2',
                    'duration'      => 80.0,
                    'percentage'    => 40.0,
                    'is_bottleneck' => true,
                ],
            ],
            'total_time' => 200.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Count bottleneck occurrences
        $bottleneckCount = substr_count($result, 'dev-toolbar-timeline-item bottleneck');
        $this->assertEquals(2, $bottleneckCount);
    }
}
