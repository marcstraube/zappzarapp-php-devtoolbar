<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the REQUEST panel content
 *
 * Displays current request information including:
 * - HTTP method, URI, status code
 * - Execution time and peak memory usage
 * - Request headers
 * - Export functionality
 */
class RequestPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'request';
    }

    /**
     * Render REQUEST tab content
     *
     * @param array<string, mixed> $data Request data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $method     = $this->escapeHtml($data['method'] ?? 'GET');
        $uri        = $this->escapeHtml($data['uri'] ?? '/');
        $statusCode = $data['status_code'] ?? 200;
        $time       = $data['execution_time'] ?? 0;
        $memory     = $data['memory_peak'] ?? 0;

        // Xdebug controls toolbar - rendered dynamically by JavaScript to always show current state
        // This ensures historical requests don't show outdated Xdebug status
        $html = '<div class="dev-toolbar-request-controls" id="dev-toolbar-request-controls-container">
            <!-- Xdebug status and controls will be injected here by JavaScript -->
        </div>';

        // Current Request section
        $html .= sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Current Request</div>
                <table class="dev-toolbar-kv-table">
                    <tr><td>Method</td><td>%s</td></tr>
                    <tr><td>URI</td><td>%s</td></tr>
                    <tr><td>Status</td><td>%d</td></tr>
                    <tr><td>Time</td><td>%.2fms</td></tr>
                    <tr><td>Memory</td><td>%.2fMB</td></tr>
                </table>
            </div>',
            $method,
            $uri,
            $statusCode,
            $time,
            $memory
        );

        // Render headers
        if (!empty($data['headers'])) {
            $html .= '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Headers</div>
                <table class="dev-toolbar-kv-table">';

            foreach ($data['headers'] as $key => $value) {
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td></tr>',
                    $this->escapeHtml($key),
                    $this->escapeHtml(is_array($value) ? (json_encode($value) ?: '[]') : (string)$value)
                );
            }

            $html .= '</table></div>';
        }

        return $html;
    }
}
