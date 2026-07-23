<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Abstract base class for panel renderers with shared utility methods
 *
 * Provides common formatting and rendering helpers used by all panel types.
 */
abstract class AbstractPanelRenderer implements PanelRendererInterface
{
    /**
     * Format milliseconds with appropriate unit
     *
     * Converts to seconds if >= 1000ms
     *
     * @param float $ms Milliseconds
     * @return string Formatted time (e.g., "45.23ms", "1.23s")
     */
    protected function formatTime(float $ms): string
    {
        if ($ms >= 1000) {
            return sprintf('%.2fs', $ms / 1000);
        }

        return sprintf('%.2fms', $ms);
    }

    /**
     * Format memory in megabytes with appropriate unit
     *
     * Converts to GB if >= 1024MB
     *
     * @param float $mb Megabytes
     * @return string Formatted memory (e.g., "45.23MB", "1.23GB")
     */
    protected function formatMemory(float $mb): string
    {
        if ($mb >= 1024) {
            return sprintf('%.2fGB', $mb / 1024);
        }

        return sprintf('%.2fMB', $mb);
    }

    /**
     * Format bytes with appropriate unit
     *
     * Automatically chooses KB, MB, GB, or TB
     *
     * @param int $bytes Bytes
     * @return string Formatted bytes (e.g., "1.5KB", "2.3MB")
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 0) {
            return '0B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? (int)floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);

        // Ensure power is within valid array bounds
        $power = max(0, min($power, 4));

        $value = $bytes / (1024 ** $power);

        if ($power === 0) {
            return sprintf('%dB', $value);
        }

        return sprintf('%.2f%s', $value, $units[$power]);
    }

    /**
     * Escape HTML special characters
     *
     * Wrapper for htmlspecialchars with consistent flags
     *
     * @param string $value Value to escape
     * @return string Escaped HTML
     */
    protected function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Render a consistent section wrapper
     *
     * Provides standard section structure with title and content
     *
     * @param string $title Section title
     * @param string $content Section content (already rendered HTML)
     * @return string HTML section
     */
    protected function renderSection(string $title, string $content): string
    {
        return sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">%s</div>
                %s
            </div>',
            $this->escapeHtml($title),
            $content
        );
    }

    /**
     * Render an empty state message
     *
     * Consistent "No data" message across panels
     *
     * @param string $message Empty state message
     * @return string HTML paragraph
     */
    protected function renderEmptyState(string $message): string
    {
        return sprintf('<p>%s</p>', $this->escapeHtml($message));
    }

    /**
     * Render a key-value table
     *
     * Common table format for displaying data pairs
     *
     * @param array<string, mixed> $data Key-value pairs
     * @return string HTML table
     */
    protected function renderKeyValueTable(array $data): string
    {
        if ($data === []) {
            return '';
        }

        $html = '<table class="dev-toolbar-kv-table">';

        foreach ($data as $key => $value) {
            $displayValue = is_array($value) ? (json_encode($value) ?: '[]') : (string)$value;
            $html .= sprintf(
                '<tr><td>%s</td><td>%s</td></tr>',
                $this->escapeHtml((string)$key),
                $this->escapeHtml($displayValue)
            );
        }

        return $html . '</table>';
    }

    /**
     * Generate CSS class for performance level
     *
     * Standardizes performance-based styling
     *
     * @param float $value Value to evaluate
     * @param float $warningThreshold Warning threshold
     * @param float $criticalThreshold Critical threshold
     * @return string CSS class (fast, slow, very-slow)
     */
    protected function getPerformanceClass(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        if ($value >= $criticalThreshold) {
            return 'very-slow';
        }

        if ($value >= $warningThreshold) {
            return 'slow';
        }

        return 'fast';
    }
}
