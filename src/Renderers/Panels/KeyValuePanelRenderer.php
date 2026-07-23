<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

/**
 * Generic fallback panel renderer.
 *
 * Renders any collector's getData() payload as a key/value table, so a
 * custom collector registered without a dedicated panel still gets a usable
 * tab instead of a dead, empty one.
 */
final class KeyValuePanelRenderer extends AbstractPanelRenderer
{
    public function renderTab(array $data): string
    {
        if ($data === []) {
            return $this->renderEmptyState('No data collected.');
        }

        return $this->renderKeyValueTable($data);
    }

    public function getPanelName(): string
    {
        return 'generic';
    }
}
