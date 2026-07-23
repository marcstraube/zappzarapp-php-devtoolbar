<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\CachePanelRenderer;

/**
 * Test CachePanelRenderer
 */
class CachePanelRendererTest extends TestCase
{
    private CachePanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CachePanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('cache', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        $this->assertStringContainsString('No cache operations', $result);
    }

    public function testRenderTabWithZeroCount(): void
    {
        $data = [
            'count'      => 0,
            'operations' => [],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0,
            'total_time' => 0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No cache operations', $result);
    }

    public function testRenderTabWithCacheGetHit(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'user:123',
                    'time' => 1.5,
                    'hit'  => true,
                ],
            ],
            'hits'       => 1,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 1.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Cache Operations (1 operations, 100.0% hit rate)', $result);
        $this->assertStringContainsString('🟢 HIT', $result);
        $this->assertStringContainsString('user:123', $result);
        $this->assertStringContainsString('1.50ms', $result);
        $this->assertStringContainsString('100.0% (1 hits, 0 misses)', $result);
    }

    public function testRenderTabWithCacheGetMiss(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'user:456',
                    'time' => 2.0,
                    'hit'  => false,
                ],
            ],
            'hits'       => 0,
            'misses'     => 1,
            'hit_rate'   => 0.0,
            'total_time' => 2.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('🔴 MISS', $result);
        $this->assertStringContainsString('user:456', $result);
        $this->assertStringContainsString('0.0% (0 hits, 1 misses)', $result);
    }

    public function testRenderTabWithCacheSet(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'set',
                    'key'  => 'session:abc123',
                    'time' => 3.5,
                    'size' => '1.2KB',
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 3.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('⚙️ SET', $result);
        $this->assertStringContainsString('session:abc123', $result);
        $this->assertStringContainsString('3.50ms', $result);
        $this->assertStringContainsString('Value Size: 1.2KB', $result);
    }

    public function testRenderTabWithCacheDelete(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'delete',
                    'key'  => 'temp:xyz',
                    'time' => 1.0,
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('🗑️ DEL', $result);
        $this->assertStringContainsString('temp:xyz', $result);
        $this->assertStringContainsString('1.00ms', $result);
    }

    public function testRenderTabWithMultipleOperations(): void
    {
        $data = [
            'count'      => 4,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'user:1',
                    'time' => 1.5,
                    'hit'  => true,
                ],
                [
                    'type' => 'get',
                    'key'  => 'user:2',
                    'time' => 1.5,
                    'hit'  => false,
                ],
                [
                    'type' => 'set',
                    'key'  => 'user:2',
                    'time' => 2.0,
                    'size' => '500B',
                ],
                [
                    'type' => 'delete',
                    'key'  => 'expired:123',
                    'time' => 0.5,
                ],
            ],
            'hits'       => 1,
            'misses'     => 1,
            'hit_rate'   => 50.0,
            'total_time' => 5.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Cache Operations (4 operations, 50.0% hit rate)', $result);
        $this->assertStringContainsString('🟢 HIT', $result);
        $this->assertStringContainsString('🔴 MISS', $result);
        $this->assertStringContainsString('⚙️ SET', $result);
        $this->assertStringContainsString('🗑️ DEL', $result);
        $this->assertStringContainsString('50.0% (1 hits, 1 misses)', $result);
        $this->assertStringContainsString('5.50ms', $result);
    }

    public function testRenderTabWithTtl(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'session:active',
                    'time' => 1.0,
                    'hit'  => true,
                    'ttl'  => 3600,
                ],
            ],
            'hits'       => 1,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('TTL: 3600s remaining', $result);
    }

    public function testRenderTabWithoutTtl(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'permanent:data',
                    'time' => 1.0,
                    'hit'  => true,
                ],
            ],
            'hits'       => 1,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringNotContainsString('TTL:', $result);
    }

    public function testRenderTabWithSetSize(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'set',
                    'key'  => 'large:object',
                    'time' => 5.0,
                    'size' => '2.5MB',
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 5.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Value Size: 2.5MB', $result);
    }

    public function testRenderTabWithoutSetSize(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'set',
                    'key'  => 'small:value',
                    'time' => 1.0,
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringNotContainsString('Value Size:', $result);
    }

    public function testRenderTabProgressBarFullHitRate(): void
    {
        $data = [
            'count'      => 10,
            'operations' => [],
            'hits'       => 10,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // 100% should render 20 filled blocks
        $this->assertStringContainsString(str_repeat('█', 20), $result);
    }

    public function testRenderTabProgressBarZeroHitRate(): void
    {
        $data = [
            'count'      => 10,
            'operations' => [],
            'hits'       => 0,
            'misses'     => 10,
            'hit_rate'   => 0.0,
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // 0% should render 20 empty blocks
        $this->assertStringContainsString(str_repeat('░', 20), $result);
    }

    public function testRenderTabProgressBarPartialHitRate(): void
    {
        $data = [
            'count'      => 10,
            'operations' => [],
            'hits'       => 5,
            'misses'     => 5,
            'hit_rate'   => 50.0,
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // 50% should render 10 filled + 10 empty blocks
        $this->assertStringContainsString(str_repeat('█', 10) . str_repeat('░', 10), $result);
    }

    public function testRenderTabEscapesHtmlInKey(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => '<script>alert("XSS")</script>',
                    'time' => 1.0,
                    'hit'  => true,
                ],
            ],
            'hits'       => 1,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInSize(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'set',
                    'key'  => 'safe:key',
                    'time' => 1.0,
                    'size' => '<script>alert("XSS")</script>',
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabContainsExpectedSections(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'get',
                    'key'  => 'test:key',
                    'time' => 1.0,
                    'hit'  => true,
                ],
            ],
            'hits'       => 1,
            'misses'     => 0,
            'hit_rate'   => 100.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-cache-stats', $result);
        $this->assertStringContainsString('dev-toolbar-cache-operation', $result);
        $this->assertStringContainsString('dev-toolbar-cache-op-header', $result);
        $this->assertStringContainsString('Operations:', $result);
    }

    public function testRenderTabHandlesMissingOperationFields(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [], // Empty operation data
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should render with defaults
        $this->assertStringContainsString('get', $result);
        $this->assertStringContainsString('0.00ms', $result);
    }

    public function testRenderTabWithUnknownOperationType(): void
    {
        $data = [
            'count'      => 1,
            'operations' => [
                [
                    'type' => 'unknown',
                    'key'  => 'test:key',
                    'time' => 1.0,
                ],
            ],
            'hits'       => 0,
            'misses'     => 0,
            'hit_rate'   => 0.0,
            'total_time' => 1.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('📝 UNKNOWN', $result);
    }
}
