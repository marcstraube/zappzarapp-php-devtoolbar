<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers;

use Zappzarapp\DevToolbar\Analyzers\PerformanceAnalyzer;
use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;
use Zappzarapp\DevToolbar\Config\MiniBarConfig;
use Zappzarapp\DevToolbar\Config\MiniBarLabel;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;

/**
 * Renders mini bar (always visible widget in bottom-right).
 *
 * Pure rendering: receives a fully-resolved {@see MiniBarConfig} and never
 * touches superglobals or the filesystem itself. Configuration parsing and
 * branch detection live in Zappzarapp\DevToolbar\Config.
 */
final readonly class MiniBarRenderer implements RendererInterface
{
    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(
        private array $collectors,
        private MiniBarConfig $config,
    ) {
    }

    public function render(): string
    {
        // Null-safe: the collector map is consumer-supplied and may omit or
        // re-key these — the mini bar must never fatal during response render.
        $requestData = isset($this->collectors['request']) ? $this->collectors['request']->getData() : [];
        $queriesData = isset($this->collectors['queries']) ? $this->collectors['queries']->getData() : [];

        $time       = $requestData['execution_time'] ?? 0;
        $memory     = $requestData['memory_peak'] ?? 0;
        $queryCount = $queriesData['count'] ?? 0;

        $labels     = $this->renderLabels($requestData);
        $alertBadge = $this->renderAlertBadge();

        return sprintf(
            '<div class="dev-toolbar-mini">
                %s
                <span class="dev-toolbar-mini-metric">%dms</span>
                <span class="dev-toolbar-mini-metric">%.1fMB</span>
                <span class="dev-toolbar-mini-metric">%d queries</span>
                %s
            </div>',
            $labels,
            (int)$time,
            $memory,
            $queryCount,
            $alertBadge
        );
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function renderLabels(array $requestData): string
    {
        $html = '';
        foreach ($this->config->labels as $label) {
            $html .= sprintf(
                '<span class="dev-toolbar-mini-label">%s</span>',
                $this->renderLabel($label, $requestData)
            );
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function renderLabel(MiniBarLabel $label, array $requestData): string
    {
        return match ($label) {
            MiniBarLabel::Branch    => $this->formatBranch($this->config->gitBranch ?? 'unknown'),
            MiniBarLabel::Route     => $this->formatRoute(
                $requestData['method'] ?? '',
                $requestData['uri'] ?? ''
            ),
            MiniBarLabel::RequestId => $this->generateRequestIdDisplay(),
            MiniBarLabel::Branding  => '⚡',
        };
    }

    private function renderAlertBadge(): string
    {
        $collectorData = array_map(
            fn(CollectorInterface $collector): array => $collector->getData(),
            $this->collectors
        );

        $alerts = PerformanceAnalyzer::analyze($collectorData, $this->config->thresholds);

        $queries    = $collectorData['queries']['queries'] ?? [];
        $analyzer   = new QueryAnalyzer();
        $nPlusOnes  = $analyzer->detectNPlusOne($queries);
        $alertCount = count($alerts) + count($nPlusOnes);

        if ($alertCount === 0) {
            return '';
        }

        $hasN1       = $nPlusOnes !== [];
        $hasCritical = array_filter($alerts, fn(array $a): bool => $a['level'] === 'critical') !== [];
        $hasWarning  = $hasN1 || array_filter($alerts, fn(array $a): bool => $a['level'] === 'warning') !== [];

        if ($hasCritical) {
            $levelClass = 'alert-critical';
        } elseif ($hasWarning) {
            $levelClass = 'alert-warning';
        } else {
            $levelClass = 'alert-info';
        }

        return sprintf(
            '<span class="dev-toolbar-mini-alert %s" title="%d performance issue%s detected"><span class="dev-toolbar-mini-alert-icon">⚠</span> %d</span>',
            $levelClass,
            $alertCount,
            $alertCount !== 1 ? 's' : '',
            $alertCount
        );
    }

    private function formatBranch(string $branch): string
    {
        $type = match (true) {
            preg_match('/^(feat|feature)\//', $branch) === 1 => 'feat',
            preg_match('/^fix\//', $branch) === 1            => 'fix',
            preg_match('/^hotfix\//', $branch) === 1         => 'hotfix',
            preg_match('/^chore\//', $branch) === 1          => 'chore',
            default                                          => 'default',
        };

        $colors = $this->config->branchColors;
        $color  = $colors[$type] ?? $colors['default'] ?? '#10b981';

        $displayName = preg_replace('/^(feature|feat|fix|hotfix|chore)\//', '', $branch) ?? $branch;

        return sprintf(
            '<span style="color: %s">%s</span>',
            htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8')
        );
    }

    private function formatRoute(string $method, string $uri): string
    {
        return sprintf(
            '%s %s',
            htmlspecialchars($method, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($uri, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Format request ID display (shortened to 12 chars).
     *
     * uniqid() is sufficient — this is a per-render display token, not a
     * security identifier, and the result is truncated to "req_XXXXXXXX".
     */
    private function generateRequestIdDisplay(): string
    {
        return htmlspecialchars(substr('req_' . uniqid(), 0, 12), ENT_QUOTES, 'UTF-8');
    }
}
