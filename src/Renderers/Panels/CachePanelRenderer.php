<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the CACHE panel content
 *
 * Displays cache operation information including:
 * - Cache statistics (hits, misses, hit rate)
 * - Operation list (GET, SET, DELETE)
 * - Hit rate visualization with progress bar
 * - TTL and value size information
 */
class CachePanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'cache';
    }

    /**
     * Render CACHE tab content
     *
     * @param array<string, mixed> $data Cache data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $operations = $data['operations'] ?? [];
        $hits       = $data['hits'] ?? 0;
        $misses     = $data['misses'] ?? 0;
        $hitRate    = $data['hit_rate'] ?? 0;
        $totalTime  = $data['total_time'] ?? 0;
        $count      = $data['count'] ?? 0;

        if ($count === 0) {
            return $this->renderEmptyState('No cache operations');
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Cache Operations (%d operations, %.1f%% hit rate)</div>
                <div class="dev-toolbar-cache-stats">
                    <div>Hit Rate: %s %.1f%% (%d hits, %d misses)</div>
                    <div>Total Time: %.2fms</div>
                </div>
            </div>',
            $count,
            $hitRate,
            $this->renderProgressBar($hitRate),
            $hitRate,
            $hits,
            $misses,
            $totalTime
        );

        $html .= '<div class="dev-toolbar-section"><div class="dev-toolbar-section-title">Operations:</div>';

        foreach ($operations as $operation) {
            $html .= $this->renderCacheOperation($operation);
        }

        return $html . '</div>';
    }

    /**
     * Render individual cache operation
     *
     * @param array<string, mixed> $operation Operation data
     * @return string Rendered HTML
     */
    private function renderCacheOperation(array $operation): string
    {
        $type = strtoupper($operation['type'] ?? 'GET');
        $key  = $this->escapeHtml($operation['key'] ?? '');
        $time = $operation['time'] ?? 0;

        $icon = match ($type) {
            'GET'    => ($operation['hit'] ?? false) ? '🟢 HIT' : '🔴 MISS',
            'SET'    => '⚙️ SET',
            'DELETE' => '🗑️ DEL',
            default  => '📝 ' . $type,
        };

        $html = sprintf(
            '<div class="dev-toolbar-cache-operation">
                <div class="dev-toolbar-cache-op-header">
                    %s <strong>%s(\'%s\')</strong> <span>%.2fms</span>
                </div>
            ',
            $icon,
            strtolower($type),
            $key,
            $time
        );

        // Show TTL for GET operations
        if ($type === 'GET' && isset($operation['ttl'])) {
            $html .= sprintf(
                '<div class="dev-toolbar-cache-ttl">TTL: %ds remaining</div>',
                $operation['ttl']
            );
        }

        // Show size for SET operations
        if ($type === 'SET' && isset($operation['size'])) {
            $html .= sprintf(
                '<div class="dev-toolbar-cache-size">Value Size: %s</div>',
                $this->escapeHtml($operation['size'])
            );
        }

        // Show call location from backtrace
        if (!empty($operation['backtrace'])) {
            $location = $operation['backtrace'][0];
            $html .= sprintf(
                '<div class="dev-toolbar-cache-location">Called at: %s:%d</div>',
                $this->escapeHtml($location['file'] ?? ''),
                $location['line'] ?? 0
            );
        }

        return $html . '</div>';
    }

    /**
     * Render a progress bar
     *
     * Visualizes percentage value using block characters
     *
     * @param float $percentage Percentage (0-100)
     * @return string HTML progress bar
     */
    private function renderProgressBar(float $percentage): string
    {
        $filled = (int)($percentage / 5); // 20 blocks total
        $empty  = 20 - $filled;

        return str_repeat('█', $filled) . str_repeat('░', $empty);
    }
}
