<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use Zappzarapp\DevToolbar\Renderers\Panels\AbstractPanelRenderer;

/**
 * Concrete test implementation of AbstractPanelRenderer.
 *
 * Exposes protected methods publicly so the abstract base class's helpers
 * (formatting, escaping, rendering, performance class) can be unit-tested
 * directly without going through a concrete panel.
 */
class TestPanelRenderer extends AbstractPanelRenderer
{
    public function renderTab(array $data): string
    {
        return '<div>Test Panel</div>';
    }

    public function getPanelName(): string
    {
        return 'test';
    }

    public function publicFormatTime(float $ms): string
    {
        return $this->formatTime($ms);
    }

    public function publicFormatMemory(float $mb): string
    {
        return $this->formatMemory($mb);
    }

    public function publicFormatBytes(int $bytes): string
    {
        return $this->formatBytes($bytes);
    }

    public function publicEscapeHtml(string $value): string
    {
        return $this->escapeHtml($value);
    }

    public function publicRenderSection(string $title, string $content): string
    {
        return $this->renderSection($title, $content);
    }

    public function publicRenderEmptyState(string $message): string
    {
        return $this->renderEmptyState($message);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function publicRenderKeyValueTable(array $data): string
    {
        return $this->renderKeyValueTable($data);
    }

    public function publicGetPerformanceClass(float $value, float $warningThreshold, float $criticalThreshold): string
    {
        return $this->getPerformanceClass($value, $warningThreshold, $criticalThreshold);
    }
}
