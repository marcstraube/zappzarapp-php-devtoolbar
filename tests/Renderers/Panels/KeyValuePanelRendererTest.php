<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\KeyValuePanelRenderer;

/**
 * Test the generic fallback panel used for collectors without a dedicated
 * panel renderer.
 */
class KeyValuePanelRendererTest extends TestCase
{
    private KeyValuePanelRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new KeyValuePanelRenderer();
    }

    public function testRendersDataAsAKeyValueTable(): void
    {
        $html = $this->renderer->renderTab(['name' => 'value', 'count' => 3]);

        $this->assertStringContainsString('dev-toolbar-kv-table', $html);
        $this->assertStringContainsString('name', $html);
        $this->assertStringContainsString('value', $html);
        $this->assertStringContainsString('3', $html);
    }

    public function testRendersAnEmptyStateForNoData(): void
    {
        $html = $this->renderer->renderTab([]);

        $this->assertStringNotContainsString('dev-toolbar-kv-table', $html);
        $this->assertStringContainsString('No data', $html);
    }

    public function testEscapesValues(): void
    {
        $html = $this->renderer->renderTab(['x' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
