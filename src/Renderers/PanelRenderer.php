<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers;

use Zappzarapp\DevToolbar\Analyzers\PerformanceAnalyzer;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\Renderers\Panels\KeyValuePanelRenderer;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererInterface;

/**
 * Renders expandable panel with tabs
 */
class PanelRenderer implements RendererInterface
{
    /**
     * @param array<string, CollectorInterface>      $collectors     keyed by collector name
     * @param array<string, PanelRendererInterface>  $panelRenderers keyed by collector name; a collector
     *                                                                without a matching entry falls back to
     *                                                                a generic key/value panel
     * @param array<string, int>|null                $thresholds     validated custom performance thresholds
     *                                                                (from MiniBarConfig), null = analyzer defaults
     */
    public function __construct(
        private readonly array $collectors,
        private readonly array $panelRenderers,
        private readonly ?array $thresholds = null,
    ) {
    }

    /**
     * @return array<string, PanelRendererInterface>
     */
    public function getPanelRenderers(): array
    {
        return $this->panelRenderers;
    }

    public function render(): string
    {
        $tabs            = $this->renderTabs();
        $alerts          = $this->renderAlerts();
        $content         = $this->renderTabContents();
        $requestSwitcher = $this->renderRequestSwitcher();

        return sprintf(
            '<div class="dev-toolbar-panel">
                <div class="dev-toolbar-panel-header">
                    %s
                    %s
                    <button class="dev-toolbar-panel-settings" title="Settings">⚙️</button>
                    <button class="dev-toolbar-panel-maximize" title="Maximize/Restore">⛶</button>
                    <button class="dev-toolbar-panel-close" title="Close">▼</button>
                </div>
                %s
                <div class="dev-toolbar-panel-content">
                    %s
                </div>
            </div>',
            $tabs,
            $requestSwitcher,
            $alerts,
            $content
        );
    }

    /**
     * Render performance alerts
     *
     * @return string Alerts HTML
     */
    private function renderAlerts(): string
    {
        // Collect all data from collectors
        $collectorData = array_map(
            fn(CollectorInterface $collector): array => $collector->getData(),
            $this->collectors
        );

        // Analyze performance with the validated thresholds resolved by CookieConfigSource
        $alerts = PerformanceAnalyzer::analyze($collectorData, $this->thresholds);

        if ($alerts === []) {
            return '';
        }

        $html = '<div class="dev-toolbar-alerts">';
        $html .= sprintf(
            '<div class="dev-toolbar-alerts-header">
                <div class="dev-toolbar-alerts-title">⚠️ Performance Alerts (%d issues detected)</div>
                <button class="dev-toolbar-alerts-dismiss" title="Dismiss all alerts">×</button>
            </div>',
            count($alerts)
        );

        foreach ($alerts as $index => $alert) {
            $levelClass = 'alert-' . ($alert['level'] ?? 'info');
            $icon       = $alert['icon'] ?? '⚪';
            $message    = htmlspecialchars($alert['message'] ?? '');
            $action     = htmlspecialchars($alert['action'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-alert %s" data-alert-index="%d">
                    <div class="dev-toolbar-alert-content">
                        <div class="dev-toolbar-alert-message">%s <strong>%s</strong></div>
                        <div class="dev-toolbar-alert-details">
                            Threshold: %s | Actual: %s
                        </div>
                        <div class="dev-toolbar-alert-action">Action: %s</div>
                    </div>
                    <button class="dev-toolbar-alert-close" title="Dismiss this alert">×</button>
                </div>',
                $levelClass,
                $index,
                $icon,
                $message,
                htmlspecialchars($alert['threshold'] ?? ''),
                htmlspecialchars($alert['actual'] ?? ''),
                $action
            );
        }

        return $html . '</div>';
    }

    /**
     * Render tab buttons
     *
     * @return string Tab buttons HTML
     */
    private function renderTabs(): string
    {
        $tabs = '';

        foreach ($this->collectors as $name => $collector) {
            $badgeCount = $collector->getBadgeCount();
            $badge      = $badgeCount === null
                ? ''
                : sprintf('<span class="dev-toolbar-panel-tab-badge">%d</span>', $badgeCount);

            $tabs .= sprintf(
                '<button class="dev-toolbar-panel-tab" data-tab="%s">
                    %s%s
                </button>',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars(strtoupper($name), ENT_QUOTES, 'UTF-8'),
                $badge
            );
        }

        return $tabs;
    }

    /**
     * Render tab content panes
     *
     * @return string Tab content HTML
     */
    private function renderTabContents(): string
    {
        $contents = '';

        foreach ($this->collectors as $name => $collector) {
            $renderer = $this->panelRenderers[$name] ?? new KeyValuePanelRenderer();
            $content  = $renderer->renderTab($collector->getData());

            $contents .= sprintf(
                '<div class="dev-toolbar-panel-tab-pane" data-tab="%s">%s</div>',
                htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
                $content
            );
        }

        return $contents;
    }

    /**
     * Render request switcher dropdown
     *
     * @return string HTML
     */
    private function renderRequestSwitcher(): string
    {
        $currentRequestId = $this->getCurrentRequestId();

        // With localStorage migration, dropdown is populated client-side by JavaScript
        // Only render the container structure - JavaScript will populate from localStorage
        $html = '<div class="dev-toolbar-request-switcher">';
        $html .= sprintf(
            '<button class="dev-toolbar-request-switcher-toggle" data-current="%s">
                <span class="dev-toolbar-request-switcher-label">Request</span>
                <span class="dev-toolbar-request-switcher-arrow">▼</span>
            </button>',
            htmlspecialchars($currentRequestId)
        );

        // Empty dropdown - JavaScript will populate via StorageManager
        $html .= '<div class="dev-toolbar-request-switcher-dropdown"></div>';

        return $html . '</div>';
    }

    /**
     * Get current request ID
     *
     * Note: With localStorage-based storage, the "current" request is always
     * the active one. Historical requests are managed client-side.
     *
     * @return string Request ID
     */
    private function getCurrentRequestId(): string
    {
        return 'current';
    }
}
