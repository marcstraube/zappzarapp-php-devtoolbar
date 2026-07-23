<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the TIMELINE panel content
 *
 * Displays request timeline visualization including:
 * - Timeline events with duration bars
 * - Percentage of total execution time
 * - Bottleneck identification
 * - Sub-events (nested operations)
 */
class TimelinePanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'timeline';
    }

    /**
     * Render TIMELINE tab content
     *
     * @param array<string, mixed> $data Timeline data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $timeline  = $data['timeline'] ?? [];
        $totalTime = $data['total_time'] ?? 0;

        if (empty($timeline)) {
            return $this->renderEmptyState('No timeline data available');
        }

        $html = sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Request Timeline (Total: %.2fms)</div>
            </div>',
            $totalTime
        );

        foreach ($timeline as $item) {
            $html .= $this->renderTimelineItem($item);
        }

        return $html;
    }

    /**
     * Render individual timeline item
     *
     * @param array<string, mixed> $item Timeline item data
     * @return string Rendered HTML
     */
    private function renderTimelineItem(array $item): string
    {
        $label        = $this->escapeHtml($item['label'] ?? '');
        $duration     = $item['duration'] ?? 0;
        $percentage   = $item['percentage'] ?? 0;
        $isBottleneck = $item['is_bottleneck'] ?? false;

        $bottleneckClass = $isBottleneck ? 'bottleneck' : '';

        $html = sprintf(
            '<div class="dev-toolbar-timeline-item %s" title="%s: %.2fms (%.1f%% of total)">
                <div class="dev-toolbar-timeline-label">%s</div>
                <div class="dev-toolbar-timeline-bar-container">
                    <div class="dev-toolbar-timeline-bar" style="width: %d%%"></div>
                    <span class="dev-toolbar-timeline-time">%.2fms (%.1f%%)</span>
                </div>',
            $bottleneckClass,
            $this->escapeHtml($label),
            $duration,
            $percentage,
            $label,
            min(100, (int)$percentage),
            $duration,
            $percentage
        );

        // Show sub-events if available
        if (!empty($item['events'])) {
            $html .= '<div class="dev-toolbar-timeline-events">';
            foreach ($item['events'] as $event) {
                $eventLabel    = $event['label'] ?? '';
                $eventDuration = $event['duration'] ?? 0;
                $html .= sprintf(
                    '<div class="dev-toolbar-timeline-subevent" title="%s: %.2fms">├─ %s (%.2fms)</div>',
                    $this->escapeHtml($eventLabel),
                    $eventDuration,
                    $this->escapeHtml($eventLabel),
                    $eventDuration
                );
            }

            $html .= '</div>';
        }

        return $html . '</div>';
    }
}
