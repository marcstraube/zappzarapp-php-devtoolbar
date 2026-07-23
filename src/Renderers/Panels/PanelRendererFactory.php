<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Renderers\Panels;

use Zappzarapp\DevToolbar\Analyzers\QueryAnalyzer;
use Zappzarapp\DevToolbar\DataCollectors\CollectorFactory;

/**
 * Builds the default panel-renderer map keyed by collector name.
 *
 * Mirrors {@see CollectorFactory}:
 * consumers registering a custom collector add a matching entry (or rely on
 * the {@see KeyValuePanelRenderer} fallback in {@see PanelRenderer}).
 */
final class PanelRendererFactory
{
    /**
     * @return array<string, PanelRendererInterface> keyed by collector name
     */
    public static function createDefault(): array
    {
        return [
            'request'    => new RequestPanelRenderer(),
            'messages'   => new MessagesPanelRenderer(),
            'exceptions' => new ExceptionsPanelRenderer(),
            'http'       => new HttpPanelRenderer(),
            'cache'      => new CachePanelRenderer(),
            'timeline'   => new TimelinePanelRenderer(),
            'queries'    => new QueriesPanelRenderer(new QueryAnalyzer()),
            'history'    => new HistoryPanelRenderer(),
        ];
    }
}
