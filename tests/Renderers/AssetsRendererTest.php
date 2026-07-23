<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\AssetsRenderer;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Test AssetsRenderer inline asset generation
 */
class AssetsRendererTest extends TestCase
{
    private AssetsRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new AssetsRenderer(NonceRegistry::generator());
    }

    public function testRendersInlineAssets(): void
    {
        $output = $this->renderer->render();

        $this->assertMatchesRegularExpression('/<style[^>]*>/', $output);
        $this->assertStringContainsString('</style>', $output);
        $this->assertMatchesRegularExpression('/<script[^>]*>/', $output);
        $this->assertStringContainsString('</script>', $output);
    }

    public function testContainsCSSVariables(): void
    {
        $output = $this->renderer->render();

        $this->assertStringContainsString('--toolbar-bg-dark', $output);
        $this->assertStringContainsString('--color-success', $output);
        $this->assertStringContainsString('.dev-toolbar-mini', $output);
        $this->assertStringContainsString('.dev-toolbar-panel', $output);
    }

    public function testContainsJavaScript(): void
    {
        $output = $this->renderer->render();

        $this->assertStringContainsString('DevToolbar', $output);
        $this->assertStringContainsString('init()', $output);
        $this->assertStringContainsString('togglePanel', $output);
        $this->assertStringContainsString('setActiveTab', $output);
    }

    public function testIsSelfContained(): void
    {
        $output = $this->renderer->render();

        // Should not reference external stylesheet/script files
        $this->assertStringNotContainsString('<link rel="stylesheet"', $output);
        $this->assertStringNotContainsString('<script src=', $output);
        $this->assertStringNotContainsString('require(', $output);
    }
}
