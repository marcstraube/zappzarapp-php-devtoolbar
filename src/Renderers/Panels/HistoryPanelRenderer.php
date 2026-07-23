<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the History panel with request tracking and trends
 *
 * The History panel provides:
 * - Request filtering controls (method, status, URI, min time)
 * - Statistics overview (total, averages, min/max)
 * - Response time trends (ASCII sparklines)
 * - Request history list
 * - Export and clear controls
 *
 * Note: Most rendering happens CLIENT-SIDE via localStorage and JavaScript.
 * This renderer creates PLACEHOLDERS that JavaScript populates dynamically.
 * Only the trends are server-rendered from available data.
 */
class HistoryPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Render the History tab content
     *
     * @param array<string, mixed> $data History data (unused — client-side rendered)
     * @return string HTML content for History tab
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") Required by PanelRendererInterface
     */
    public function renderTab(array $data): string
    {
        return $this->renderFilters()
            . $this->renderStatistics()
            . $this->renderTrends()
            . $this->renderRequestList();
    }

    /**
     * Get the panel name identifier
     *
     * @return string Panel name ('history')
     */
    public function getPanelName(): string
    {
        return 'history';
    }

    /**
     * Render history filter controls and action buttons
     *
     * Provides sticky toolbar with:
     * - Left: Filter controls (Method, Status, URI, Min Time, Reset)
     * - Right: Action buttons (Export JSON, Export CSV, Clear History)
     *
     * JavaScript handles filtering logic and button actions.
     *
     * @return string HTML for filter controls and actions
     */
    private function renderFilters(): string
    {
        return '<div class="dev-toolbar-history-filters">
            <div class="dev-toolbar-history-filter-controls">
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-method">Method</label>
                    <select id="history-filter-method" class="dev-toolbar-filter-select">
                        <option value="">All Methods</option>
                        <option value="GET">GET</option>
                        <option value="POST">POST</option>
                        <option value="PUT">PUT</option>
                        <option value="DELETE">DELETE</option>
                        <option value="PATCH">PATCH</option>
                    </select>
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-status">Status</label>
                    <select id="history-filter-status" class="dev-toolbar-filter-select">
                        <option value="">All Status</option>
                        <option value="2">2xx Success</option>
                        <option value="3">3xx Redirect</option>
                        <option value="4">4xx Client Error</option>
                        <option value="5">5xx Server Error</option>
                    </select>
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-uri">URI Contains</label>
                    <input type="text" id="history-filter-uri" class="dev-toolbar-filter-input" placeholder="Search URI...">
                </div>
                <div class="dev-toolbar-filter-group">
                    <label for="history-filter-min-time">Min Time (ms)</label>
                    <input type="number" id="history-filter-min-time" class="dev-toolbar-filter-input" placeholder="0" min="0" step="10">
                </div>
                <div class="dev-toolbar-filter-group">
                    <button id="history-filter-reset" class="dev-toolbar-btn dev-toolbar-btn-secondary">Reset Filters</button>
                </div>
            </div>
            <div class="dev-toolbar-history-actions">
                <button id="history-export-json" class="dev-toolbar-btn dev-toolbar-btn-secondary" title="Export all requests as JSON">{ } JSON</button>
                <button id="history-export-csv" class="dev-toolbar-btn dev-toolbar-btn-secondary" title="Export summary as CSV">⊞ CSV</button>
                <button id="history-clear" class="dev-toolbar-btn dev-toolbar-btn-secondary dev-toolbar-btn-danger-subtle" title="Clear all history">× Clear</button>
            </div>
        </div>';
    }

    /**
     * Render statistics placeholder (client-side populated)
     *
     * Creates placeholder stat cards with data-history-stat attributes.
     * JavaScript reads request history from localStorage and calculates:
     * - Total request count
     * - Average execution time
     * - Average memory usage
     * - Average query count
     * - Fastest request time
     * - Slowest request time
     *
     * @return string HTML for statistics section with placeholders
     */
    private function renderStatistics(): string
    {
        // Render placeholder - JavaScript will populate from localStorage
        $html = '<div class="dev-toolbar-section">
            <div class="dev-toolbar-section-title">Statistics</div>
            <div class="dev-toolbar-history-stats-grid" id="dev-toolbar-history-stats-grid">';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Total Requests</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="total">0</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Time</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_time">0ms</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Memory</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_memory">0MB</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Avg Queries</div>
                <div class="dev-toolbar-history-stat-value" data-history-stat="avg_queries">0</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Fastest</div>
                <div class="dev-toolbar-history-stat-value fast" data-history-stat="fastest">0ms</div>
            </div>';

        $html .= '<div class="dev-toolbar-history-stat-card">
                <div class="dev-toolbar-history-stat-label">Slowest</div>
                <div class="dev-toolbar-history-stat-value slow" data-history-stat="slowest">0ms</div>
            </div>';

        return $html . '</div></div>';
    }

    /**
     * Render performance trends placeholder (client-side populated)
     *
     * Creates container for SVG line charts (Time, Memory, Queries).
     * JavaScript loads request history from localStorage and renders
     * interactive SVG charts with synchronized crosshairs and threshold lines.
     *
     * The section is hidden by JavaScript if insufficient data is available.
     *
     * @return string HTML for trends section placeholder
     */
    private function renderTrends(): string
    {
        return '<div class="dev-toolbar-section" id="dev-toolbar-history-trends-section">
                <div class="dev-toolbar-section-title">Performance Trends</div>
                <div class="dev-toolbar-history-trends" id="dev-toolbar-trends-container"></div>
            </div>';
    }

    /**
     * Render request list placeholder (client-side populated)
     *
     * Creates a placeholder container with loading message.
     * JavaScript loads request history from localStorage and dynamically
     * generates the request list with details for each historical request.
     *
     * The list includes:
     * - Request ID for switching between stored requests
     * - Method, URI, status code
     * - Execution time, memory usage
     * - Query count, cache operations
     * - Timestamp
     *
     * @return string HTML for request list placeholder
     */
    private function renderRequestList(): string
    {
        // Render placeholder - JavaScript will populate from localStorage
        return '<div class="dev-toolbar-section">
            <div class="dev-toolbar-section-title" id="history-list-title">Request History (<span id="history-list-count">0</span>)</div>
            <div class="dev-toolbar-history-request-list" id="history-request-list-container">
                <p style="color: #888;">Loading history from localStorage...</p>
            </div>
        </div>';
    }

}
