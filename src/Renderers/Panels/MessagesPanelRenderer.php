<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the MESSAGES panel content
 *
 * Displays log messages collected during request execution.
 * Shows message level, timestamp, and content with appropriate styling.
 */
class MessagesPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'messages';
    }

    /**
     * Render MESSAGES tab content
     *
     * @param array<string, mixed> $data Messages data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $messages = $data['messages'] ?? [];

        if (empty($messages)) {
            return $this->renderEmptyState('No log messages');
        }

        $html = '';

        foreach ($messages as $message) {
            $level     = $message['level'] ?? 'info';
            $levelName = $this->escapeHtml($message['level_name'] ?? 'INFO');
            $text      = $this->escapeHtml($message['message'] ?? '');
            $time      = $this->escapeHtml($message['datetime'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-message %s">
                    <div class="dev-toolbar-message-header">
                        <span class="dev-toolbar-message-level">%s</span>
                        <span class="dev-toolbar-message-time">%s</span>
                    </div>
                    <div class="dev-toolbar-message-text">%s</div>
                </div>',
                $level,
                $levelName,
                $time,
                $text
            );
        }

        return $html;
    }
}
