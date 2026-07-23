<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers;

use Random\RandomException;
use Zappzarapp\DevToolbar\Config\MiniBarConfig;
use Zappzarapp\DevToolbar\Config\MiniBarLabel;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\Renderers\Panels\KeyValuePanelRenderer;
use Zappzarapp\DevToolbar\Utils\RequestUtils;
use Zappzarapp\Security\Csp\Nonce\NonceProvider;

/**
 * Injects DevToolbar data as JavaScript for localStorage storage
 *
 * Renders current request data as <script> tag with JSON payload.
 * Eliminates AJAX round-trips by embedding data directly in HTML.
 */
class DataInjectionRenderer implements RendererInterface
{
    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(
        private readonly array $collectors,
        private readonly NonceProvider $nonce,
        private readonly MiniBarConfig $config,
        private readonly PanelRenderer $panelRenderer,
    ) {
    }

    /**
     * Render data injection script tag
     *
     * @return string JavaScript tag with window.__DEV_TOOLBAR_DATA__ and migration data
     * @throws RandomException If secure random source is unavailable
     */
    public function render(): string
    {
        $nonce   = $this->nonce->get();
        $scripts = '';

        // Inject current request data
        $requestId = RequestUtils::generateId();
        $metadata  = $this->extractMetadata($requestId);
        $tabs      = $this->renderAllTabs();
        $jsonData  = $this->extractJsonData();

        $currentPayload = [
            'id'        => $requestId,
            'metadata'  => $metadata,
            'tabs'      => $tabs,
            'json_data' => $jsonData,
        ];

        $json = json_encode($currentPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $scripts .= sprintf(
            '<script nonce="%s">window.__DEV_TOOLBAR_DATA__ = %s;</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $json
        );

        // Inject Xdebug configuration (live state, not historical)
        $xdebugConfig = [
            'enabled' => extension_loaded('xdebug'),
        ];
        $xdebugJson = json_encode($xdebugConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $scripts . sprintf(
            '<script nonce="%s">window.__XDEBUG_CONFIG__ = %s;</script>',
            htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'),
            $xdebugJson
        );
    }

    /**
     * Extract lightweight metadata for request
     *
     * @param string $requestId Generated request ID
     * @return array<string, mixed> Metadata array
     */
    private function extractMetadata(string $requestId): array
    {
        // Get request data from collector
        $requestData = isset($this->collectors['request']) ? $this->collectors['request']->getData() : [];
        $queryData   = isset($this->collectors['queries']) ? $this->collectors['queries']->getData() : [];

        // Collect badge counts for all tabs (0 when the collector has no badge)
        $badgeCounts = [];
        foreach ($this->collectors as $name => $collector) {
            $badgeCounts[$name] = $collector->getBadgeCount() ?? 0;
        }

        $timestamp = time();

        return [
            'id'                 => $requestId,
            'method'             => $requestData['method'] ?? 'GET',
            'uri'                => $requestData['uri'] ?? '/',
            'status'             => $requestData['status_code'] ?? 200,
            'time'               => $requestData['execution_time'] ?? 0,
            'memory'             => $requestData['memory_peak'] ?? 0,
            'query_count'        => $queryData['count'] ?? 0,
            'timestamp'          => $timestamp,
            'date'               => date('Y-m-d H:i:s', $timestamp),
            'badge_counts'       => $badgeCounts,
            // Git branch, colors and the primary label type come from the
            // validated MiniBarConfig (resolved once via CookieConfigSource),
            // not from ad-hoc superglobal reads.
            'minibar_label_type' => ($this->config->labels[0] ?? MiniBarLabel::Branding)->value,
            'git_branch'         => $this->config->gitBranch,
            'branch_colors'      => $this->config->branchColors,
            'request_id'         => $requestId,
        ];
    }

    /**
     * Extract JSON data from collectors
     *
     * Returns JSON data from all collectors for export.
     * This data is cleaner for external consumption than rendered HTML.
     *
     * @return array<string, mixed> Tab name => JSON data
     */
    private function extractJsonData(): array
    {
        $jsonData = [];

        foreach ($this->collectors as $name => $collector) {
            $jsonData[$name] = $collector->getData();
        }

        return $jsonData;
    }

    /**
     * Render all tab contents as key-value pairs
     *
     * @return array<string, string> Tab name => HTML content
     */
    private function renderAllTabs(): array
    {
        $tabs           = [];
        $panelRenderers = $this->panelRenderer->getPanelRenderers();

        foreach ($this->collectors as $name => $collector) {
            $renderer    = $panelRenderers[$name] ?? new KeyValuePanelRenderer();
            $tabs[$name] = $renderer->renderTab($collector->getData());
        }

        return $tabs;
    }
}
