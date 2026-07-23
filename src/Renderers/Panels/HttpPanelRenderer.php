<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the HTTP panel content
 *
 * Displays HTTP client request information including:
 * - Request method, URL, status code
 * - Response time and performance indicators
 * - Request headers and body (when available)
 * - Call location (backtrace)
 */
class HttpPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'http';
    }

    /**
     * Render HTTP tab content
     *
     * @param array<string, mixed> $data HTTP data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $requests  = $data['requests'] ?? [];
        $totalTime = $data['total_time'] ?? 0;
        $count     = $data['count'] ?? 0;

        if ($count === 0) {
            return $this->renderEmptyState('No HTTP requests made');
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">HTTP Client (%d %s, %.2fms total)</div>
            </div>',
            $count,
            $count === 1 ? 'request' : 'requests',
            $totalTime
        );

        foreach ($requests as $request) {
            $html .= $this->renderHttpRequest($request);
        }

        return $html;
    }

    /**
     * Render individual HTTP request
     *
     * @param array<string, mixed> $request Request data
     * @return string Rendered HTML
     */
    private function renderHttpRequest(array $request): string
    {
        $method    = $this->escapeHtml($request['method'] ?? 'GET');
        $url       = $this->escapeHtml($request['url'] ?? '');
        $time      = $request['time'] ?? 0;
        $status    = $request['status'] ?? 0;
        $perfLevel = $request['performance_level'] ?? 'good';

        $icon = match ($perfLevel) {
            'good'     => '🟢',
            'warning'  => '🟡',
            'critical' => '🔴',
            default    => '⚪',
        };

        $html = sprintf(
            '<div class="dev-toolbar-http-request %s">
                <div class="dev-toolbar-http-header">
                    %s <strong>%s</strong> %s <span class="dev-toolbar-http-time">(%.2fms)</span>
                </div>
                <div class="dev-toolbar-http-status">Status: %d</div>',
            $this->escapeHtml($perfLevel),
            $icon,
            $method,
            $url,
            $time,
            $status
        );

        // Show location if available
        if (!empty($request['backtrace'])) {
            $location = $request['backtrace'][0];
            $html .= sprintf(
                '<div class="dev-toolbar-http-location">Called at: %s:%d</div>',
                $this->escapeHtml($location['file'] ?? ''),
                $location['line'] ?? 0
            );
        }

        // Response headers (collapsible)
        if (!empty($request['headers'])) {
            $html .= '<details class="dev-toolbar-http-details">';
            $html .= '<summary>Response Headers</summary>';
            $html .= '<pre class="dev-toolbar-http-pre">';
            $html .= $this->formatHeaders($request['headers']);
            $html .= '</pre></details>';
        }

        // Response body (collapsible)
        if (!empty($request['body'])) {
            $body = is_string($request['body']) ? $request['body'] : '';
            $html .= '<details class="dev-toolbar-http-details">';
            $html .= '<summary>Response Body</summary>';
            $html .= '<pre class="dev-toolbar-http-pre">';
            $html .= $this->escapeHtml($this->truncateBody($body));
            $html .= '</pre></details>';
        }

        return $html . '</div>';
    }

    /**
     * Format response headers for display
     *
     * @param array<string|int, string|array<string>> $headers Headers array
     * @return string Escaped formatted headers
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];

        foreach ($headers as $key => $value) {
            $val = is_array($value) ? implode(', ', $value) : $value;

            if (is_int($key)) {
                // Raw header line (e.g., from file_get_contents)
                $lines[] = $this->escapeHtml($val);
            } else {
                // Key-value pair
                $lines[] = $this->escapeHtml($key . ': ' . $val);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Truncate response body for display
     *
     * @param string $body Response body
     * @return string Truncated body
     */
    private function truncateBody(string $body): string
    {
        $maxLength = 2000;

        if (strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength) . "\n\n... (truncated, " . strlen($body) . ' bytes total)';
    }
}
