<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\HistoryPanelRenderer;

/**
 * Tests for the sparkline placeholder rendering in HistoryPanelRenderer.
 *
 * Sparkline rendering itself is client-side; these tests assert that the
 * server emits the empty placeholder element regardless of the trends
 * input shape (empty / ascending / descending / floats / extremes etc.),
 * so the JavaScript layer always finds the container it expects.
 */
class HistoryPanelRendererSparklineTest extends TestCase
{
    private const string TRENDS_PLACEHOLDER_PATTERN = '/<div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"><\/div>/';

    private HistoryPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HistoryPanelRenderer();
    }

    public function testSparklineGenerationWithEmptyArray(): void
    {
        $output = $this->renderer->renderTab(['trends' => ['time' => []]]);

        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithAscendingValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [10.0, 20.0, 30.0, 40.0, 50.0]],
        ]);

        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithDescendingValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [50.0, 40.0, 30.0, 20.0, 10.0]],
        ]);

        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithFloatingPointValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [123.456, 234.567, 345.678, 456.789]],
        ]);

        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithMixedValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [100.0, 50.0, 200.0, 75.0, 150.0]],
        ]);

        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithExtremeValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [1.0, 1000.0, 500.0, 1500.0, 100.0]],
        ]);

        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineGenerationWithTwoIdenticalValues(): void
    {
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [100.0, 100.0]],
        ]);

        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }

    public function testSparklineNormalizationLogic(): void
    {
        // Sparkline normalization happens client-side; this test asserts
        // the placeholder is emitted for the full 0–100 span we feed JS.
        $output = $this->renderer->renderTab([
            'trends' => ['time' => [0.0, 12.5, 25.0, 37.5, 50.0, 62.5, 75.0, 87.5, 100.0]],
        ]);

        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression(self::TRENDS_PLACEHOLDER_PATTERN, $output);
    }
}
