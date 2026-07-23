<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Interface for panel renderers
 *
 * Each panel type (queries, cache, messages, etc.) implements this interface.
 */
interface PanelRendererInterface
{
    /**
     * Render panel tab content
     *
     * @param array<string, mixed> $data Collector data
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string;

    /**
     * Get panel name/identifier
     *
     * @return string Panel name (e.g., 'queries', 'cache')
     */
    public function getPanelName(): string;
}
