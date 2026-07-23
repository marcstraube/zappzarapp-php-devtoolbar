<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Renders the EXCEPTIONS panel content
 *
 * Displays exceptions thrown during request execution.
 * Shows exception class, message, file location, and handled status.
 */
class ExceptionsPanelRenderer extends AbstractPanelRenderer
{
    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'exceptions';
    }

    /**
     * Render EXCEPTIONS tab content
     *
     * @param array<string, mixed> $data Exceptions data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $exceptions = $data['exceptions'] ?? [];

        if (empty($exceptions)) {
            return $this->renderEmptyState('No exceptions thrown');
        }

        $html = '';

        foreach ($exceptions as $exception) {
            $handled = $exception['handled'] ?? true;
            $class   = $this->escapeHtml($exception['class'] ?? 'Exception');
            $message = $this->escapeHtml($exception['message'] ?? '');
            $file    = $this->escapeHtml($exception['file'] ?? '');
            $line    = $exception['line'] ?? 0;

            $html .= sprintf(
                '<div class="dev-toolbar-exception %s">
                    <div class="dev-toolbar-exception-class">%s</div>
                    <div class="dev-toolbar-exception-message">%s</div>
                    <div class="dev-toolbar-exception-location">%s:%d</div>
                </div>',
                $handled ? 'handled' : '',
                $class,
                $message,
                $file,
                $line
            );
        }

        return $html;
    }
}
