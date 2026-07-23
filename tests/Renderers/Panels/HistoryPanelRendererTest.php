<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\HistoryPanelRenderer;

/**
 * Tests for HistoryPanelRenderer
 *
 * Covers:
 * - Main tab rendering with all sections
 * - Filter controls rendering
 * - Statistics placeholder rendering (client-side populated)
 * - Trends with sparklines (various data patterns)
 * - Request list placeholder rendering
 * - Export controls rendering
 * - Sparkline generation algorithm
 * - Edge cases (empty data, missing keys, null values)
 */
class HistoryPanelRendererTest extends TestCase
{
    private HistoryPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HistoryPanelRenderer();
    }

    public function testGetPanelName(): void
    {
        $this->assertSame('history', $this->renderer->getPanelName());
    }

    public function testRenderTabWithEmptyData(): void
    {
        $output = $this->renderer->renderTab([]);

        // Should contain all main sections even with empty data
        $this->assertStringContainsString('dev-toolbar-history-filters', $output);
        $this->assertStringContainsString('dev-toolbar-history-actions', $output);
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringContainsString('Request History', $output);

        // Should contain trends section placeholder (JavaScript will hide if no data)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
    }

    public function testRenderTabWithTrendData(): void
    {
        $data = [
            'trends' => [
                'time' => [100.5, 150.2, 200.8, 120.3, 180.5],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should contain trends section placeholder (JavaScript will populate)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-trends-container', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);

        // Sparkline element should be empty (JavaScript will populate)
        $this->assertMatchesRegularExpression('/<div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"><\/div>/', $output);
    }

    public function testRenderFilters(): void
    {
        $output = $this->renderer->renderTab([]);

        // Method filter
        $this->assertStringContainsString('history-filter-method', $output);
        $this->assertStringContainsString('<option value="GET">GET</option>', $output);
        $this->assertStringContainsString('<option value="POST">POST</option>', $output);
        $this->assertStringContainsString('<option value="PUT">PUT</option>', $output);
        $this->assertStringContainsString('<option value="DELETE">DELETE</option>', $output);
        $this->assertStringContainsString('<option value="PATCH">PATCH</option>', $output);

        // Status filter
        $this->assertStringContainsString('history-filter-status', $output);
        $this->assertStringContainsString('<option value="2">2xx Success</option>', $output);
        $this->assertStringContainsString('<option value="3">3xx Redirect</option>', $output);
        $this->assertStringContainsString('<option value="4">4xx Client Error</option>', $output);
        $this->assertStringContainsString('<option value="5">5xx Server Error</option>', $output);

        // URI filter
        $this->assertStringContainsString('history-filter-uri', $output);
        $this->assertStringContainsString('placeholder="Search URI..."', $output);

        // Min time filter
        $this->assertStringContainsString('history-filter-min-time', $output);
        $this->assertStringContainsString('type="number"', $output);
        $this->assertStringContainsString('min="0"', $output);
        $this->assertStringContainsString('step="10"', $output);

        // Reset button
        $this->assertStringContainsString('history-filter-reset', $output);
        $this->assertStringContainsString('Reset Filters', $output);
    }

    public function testRenderStatisticsPlaceholders(): void
    {
        $output = $this->renderer->renderTab([]);

        // Statistics section
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringContainsString('dev-toolbar-history-stats-grid', $output);

        // All stat cards with data attributes (JavaScript will populate)
        $this->assertStringContainsString('data-history-stat="total"', $output);
        $this->assertStringContainsString('data-history-stat="avg_time"', $output);
        $this->assertStringContainsString('data-history-stat="avg_memory"', $output);
        $this->assertStringContainsString('data-history-stat="avg_queries"', $output);
        $this->assertStringContainsString('data-history-stat="fastest"', $output);
        $this->assertStringContainsString('data-history-stat="slowest"', $output);

        // Default placeholder values
        $this->assertMatchesRegularExpression('/data-history-stat="total">0</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_time">0ms</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_memory">0MB</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="avg_queries">0</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="fastest">0ms</', $output);
        $this->assertMatchesRegularExpression('/data-history-stat="slowest">0ms</', $output);

        // Stat labels
        $this->assertStringContainsString('Total Requests', $output);
        $this->assertStringContainsString('Avg Time', $output);
        $this->assertStringContainsString('Avg Memory', $output);
        $this->assertStringContainsString('Avg Queries', $output);
        $this->assertStringContainsString('Fastest', $output);
        $this->assertStringContainsString('Slowest', $output);
    }

    public function testRenderTrendsWithEmptyData(): void
    {
        $data = [
            'trends' => [],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends placeholder (JavaScript will populate/hide)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-trends-container', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
    }

    public function testRenderTrendsWithEmptyTimeArray(): void
    {
        $data = [
            'trends' => [
                'time' => [],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends placeholder (JavaScript will hide if no data)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
    }

    public function testRenderTrendsWithSingleValue(): void
    {
        $data = [
            'trends' => [
                'time' => [100.5],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends placeholder (JavaScript will populate)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression('/<div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"><\/div>/', $output);
    }

    public function testRenderTrendsWithMultipleValues(): void
    {
        $data = [
            'trends' => [
                'time' => [50.0, 100.0, 150.0, 200.0, 250.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends placeholder (JavaScript will populate)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression('/<div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"><\/div>/', $output);
    }

    public function testRenderTrendsWithIdenticalValues(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 100.0, 100.0, 100.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Should render trends placeholder (JavaScript will populate)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
        $this->assertMatchesRegularExpression('/<div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"><\/div>/', $output);
    }

    public function testRenderRequestListPlaceholder(): void
    {
        $output = $this->renderer->renderTab([]);

        // Request list section
        $this->assertStringContainsString('Request History', $output);
        $this->assertStringContainsString('history-list-title', $output);
        $this->assertStringContainsString('history-list-count', $output);
        $this->assertStringContainsString('history-request-list-container', $output);

        // Loading placeholder message
        $this->assertStringContainsString('Loading history from localStorage...', $output);

        // Count placeholder (JavaScript will update)
        $this->assertMatchesRegularExpression('/<span id="history-list-count">0<\/span>/', $output);
    }

    public function testRenderExportControls(): void
    {
        $output = $this->renderer->renderTab([]);

        // Action buttons (now in filter bar)
        $this->assertStringContainsString('dev-toolbar-history-actions', $output);

        // Export buttons
        $this->assertStringContainsString('history-export-json', $output);
        $this->assertStringContainsString('JSON', $output);
        $this->assertStringContainsString('history-export-csv', $output);
        $this->assertStringContainsString('CSV', $output);

        // Clear button
        $this->assertStringContainsString('history-clear', $output);
        $this->assertStringContainsString('Clear', $output);
        $this->assertStringContainsString('dev-toolbar-btn-danger', $output);
    }

    public function testCompleteTabStructure(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 150.0, 200.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Verify all major sections are present in correct order
        $filterPos  = strpos($output, 'dev-toolbar-history-filters');
        $actionsPos = strpos($output, 'dev-toolbar-history-actions');
        $statsPos   = strpos($output, 'Statistics');
        $trendsPos  = strpos($output, 'Performance Trends');
        $listPos    = strpos($output, 'Request History');

        // Ensure all sections exist
        $this->assertNotFalse($filterPos);
        $this->assertNotFalse($actionsPos);
        $this->assertNotFalse($statsPos);
        $this->assertNotFalse($trendsPos);
        $this->assertNotFalse($listPos);

        // Ensure sections appear in correct order
        // Actions are inside Filters
        $this->assertLessThan($statsPos, $filterPos, 'Filters should come before Statistics');
        $this->assertLessThan($trendsPos, $statsPos, 'Statistics should come before Trends');
        $this->assertLessThan($listPos, $trendsPos, 'Trends should come before Request List');

        // Actions are within Filters div, so they come after filter controls but before Statistics section
        $this->assertGreaterThan($filterPos, $actionsPos, 'Actions should be inside Filters');
    }

    public function testDataStructureWithMissingKeys(): void
    {
        // Test with trends key missing
        $data = [];

        $output = $this->renderer->renderTab($data);

        // Should render all sections including trends placeholder
        $this->assertStringContainsString('dev-toolbar-history-filters', $output);
        $this->assertStringContainsString('Statistics', $output);
        $this->assertStringContainsString('Performance Trends', $output);
    }

    public function testDataStructureWithNullValues(): void
    {
        $data = [
            'trends' => null,
        ];

        $output = $this->renderer->renderTab($data);

        // Should handle null trends gracefully (render placeholder)
        $this->assertStringContainsString('Performance Trends', $output);
        $this->assertStringContainsString('dev-toolbar-history-trends-section', $output);
    }

    public function testAllSectionsUseCorrectCssClasses(): void
    {
        $data = [
            'trends' => [
                'time' => [100.0, 200.0],
            ],
        ];

        $output = $this->renderer->renderTab($data);

        // Verify standard DevToolbar CSS classes are used
        $this->assertStringContainsString('dev-toolbar-section', $output);
        $this->assertStringContainsString('dev-toolbar-section-title', $output);
        $this->assertStringContainsString('dev-toolbar-filter-group', $output);
        $this->assertStringContainsString('dev-toolbar-filter-select', $output);
        $this->assertStringContainsString('dev-toolbar-filter-input', $output);
        $this->assertStringContainsString('dev-toolbar-btn', $output);
        $this->assertStringContainsString('dev-toolbar-btn-secondary', $output);
        $this->assertStringContainsString('dev-toolbar-btn-danger-subtle', $output);
    }

}
