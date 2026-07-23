<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;

/**
 * Renders the QUERIES panel content
 *
 * Displays database query information including:
 * - Query summary (count, total time, average time)
 * - N+1 query detection warnings with suggestions
 * - Slow query detection with optimization hints
 * - Individual query details with bindings and execution time
 * - Call location backtrace
 *
 * Depends on QueryAnalyzer for N+1 and slow query detection.
 */
class QueriesPanelRenderer extends AbstractPanelRenderer
{
    /**
     * @param QueryAnalyzer $analyzer Query analyzer for N+1 and slow query detection
     */
    public function __construct(private readonly QueryAnalyzer $analyzer)
    {
    }

    /**
     * Get panel identifier
     *
     * @return string Panel name
     */
    public function getPanelName(): string
    {
        return 'queries';
    }

    /**
     * Render QUERIES tab content
     *
     * @param array<string, mixed> $data Query data from collector
     * @return string Rendered HTML
     */
    public function renderTab(array $data): string
    {
        $queries = $data['queries'] ?? [];

        if (empty($queries)) {
            return $this->renderEmptyState('No queries executed');
        }

        $totalTime = $data['total_time'] ?? 0;

        // Build HTML output
        $html = $this->renderSummary(count($queries), $totalTime);
        $html .= $this->renderNPlusOneWarnings($queries);
        $html .= $this->renderSlowQueryWarnings($queries);

        return $html . $this->renderQueryList($queries);
    }

    /**
     * Render query summary section
     *
     * @param int $queryCount Number of queries executed
     * @param float $totalTime Total execution time in milliseconds
     * @return string HTML
     */
    private function renderSummary(int $queryCount, float $totalTime): string
    {
        $avgTime = $queryCount > 0 ? $totalTime / $queryCount : 0;

        return sprintf(
            '<div class="dev-toolbar-section">
                <div class="dev-toolbar-section-title">Summary: %d %s in %.2fms (avg: %.2fms)</div>
            </div>',
            $queryCount,
            $queryCount === 1 ? 'query' : 'queries',
            $totalTime,
            $avgTime
        );
    }

    /**
     * Render N+1 query warnings section
     *
     * @param array<int, array<string, mixed>> $queries List of queries
     * @return string HTML (empty if no N+1 detected)
     */
    private function renderNPlusOneWarnings(array $queries): string
    {
        $nPlusOnes = $this->analyzer->detectNPlusOne($queries);

        if ($nPlusOnes === []) {
            return '';
        }

        $html = '<div class="dev-toolbar-section">';
        $html .= sprintf(
            '<div class="dev-toolbar-section-title">⚠️ N+1 Query Detected! (%d %s)</div>',
            count($nPlusOnes),
            count($nPlusOnes) === 1 ? 'pattern' : 'patterns'
        );

        foreach ($nPlusOnes as $nPlusOne) {
            $html .= sprintf(
                '<div class="dev-toolbar-n-plus-one">
                    <div><strong>Pattern:</strong> %s</div>
                    <div><strong>Executed:</strong> %d times with different parameters</div>
                    <div><strong>Total Time:</strong> %.2fms (avg: %.2fms)</div>
                    <div><strong>Called from:</strong> %s</div>
                    <div class="dev-toolbar-suggestion">💡 Suggestion: %s</div>
                </div>',
                $this->escapeHtml($nPlusOne['pattern']),
                $nPlusOne['count'],
                $nPlusOne['total_time'],
                $nPlusOne['avg_time'],
                $this->escapeHtml($nPlusOne['location']),
                nl2br($this->escapeHtml($nPlusOne['suggestion']))
            );
        }

        return $html . '</div>';
    }

    /**
     * Render slow query warnings section
     *
     * @param array<int, array<string, mixed>> $queries List of queries
     * @return string HTML (empty if no slow queries detected)
     */
    private function renderSlowQueryWarnings(array $queries): string
    {
        $slowQueries = $this->analyzer->detectSlowQueries($queries);

        if ($slowQueries === []) {
            return '';
        }

        $html = '<div class="dev-toolbar-section">';
        $html .= sprintf(
            '<div class="dev-toolbar-section-title">🐌 Slow Queries Detected! (%d %s)</div>',
            count($slowQueries),
            count($slowQueries) === 1 ? 'query' : 'queries'
        );

        foreach ($slowQueries as $slowQuery) {
            $html .= sprintf(
                '<div class="dev-toolbar-slow-query">
                    <div><strong>SQL:</strong> %s</div>
                    <div><strong>Time:</strong> %.2fms</div>
                    <div><strong>Called from:</strong> %s</div>
                    <div class="dev-toolbar-suggestion">💡 Suggestion: %s</div>
                </div>',
                $this->escapeHtml($slowQuery['sql']),
                $slowQuery['time'],
                $this->escapeHtml($slowQuery['location']),
                $this->escapeHtml($slowQuery['suggestion'])
            );
        }

        return $html . '</div>';
    }

    /**
     * Render the list of all executed queries
     *
     * @param array<int, array<string, mixed>> $queries List of queries
     * @return string HTML
     */
    private function renderQueryList(array $queries): string
    {
        $html = '<div class="dev-toolbar-section"><div class="dev-toolbar-section-title">Query List:</div>';

        foreach ($queries as $query) {
            $html .= $this->renderQuery($query);
        }

        return $html . '</div>';
    }

    /**
     * Render a single query entry
     *
     * @param array<string, mixed> $query Query data
     * @return string HTML
     */
    private function renderQuery(array $query): string
    {
        $time     = $query['time'] ?? 0;
        $sql      = $this->escapeHtml($query['sql'] ?? '');
        $bindings = $query['bindings'] ?? [];

        // Determine performance class based on execution time
        $timeClass  = $this->getPerformanceClass($time, 100.0, 500.0);
        $queryClass = $timeClass;

        $html = sprintf(
            '<div class="dev-toolbar-query %s">
                <div class="dev-toolbar-query-header">
                    <span class="dev-toolbar-query-time %s">%.2fms</span>
                </div>
                <div class="dev-toolbar-query-sql">%s</div>',
            $queryClass,
            $timeClass,
            $time,
            $sql
        );

        // Render bindings if present
        if (!empty($bindings)) {
            $bindingsJson = json_encode($bindings, JSON_PRETTY_PRINT) ?: '[]';
            $html .= sprintf(
                '<div class="dev-toolbar-query-bindings">Bindings: %s</div>',
                $this->escapeHtml($bindingsJson)
            );
        }

        // Render backtrace if available
        if (!empty($query['backtrace'])) {
            $html .= $this->renderBacktrace($query['backtrace']);
        }

        return $html . '</div>';
    }

    /**
     * Render query backtrace information
     *
     * @param array<int, array<string, mixed>> $backtrace Stack trace
     * @return string HTML
     */
    private function renderBacktrace(array $backtrace): string
    {
        if ($backtrace === []) {
            return '';
        }

        $html = '<div class="dev-toolbar-query-backtrace">';
        $html .= '<div class="dev-toolbar-backtrace-title">Called from:</div>';

        foreach ($backtrace as $frame) {
            $file     = $this->escapeHtml($frame['file'] ?? 'unknown');
            $line     = $frame['line'] ?? 0;
            $function = $this->escapeHtml($frame['function'] ?? '');

            $html .= sprintf(
                '<div class="dev-toolbar-backtrace-frame">%s:%d %s</div>',
                $file,
                $line,
                $function !== '' && $function !== '0' ? sprintf('in %s()', $function) : ''
            );
        }

        return $html . '</div>';
    }
}
