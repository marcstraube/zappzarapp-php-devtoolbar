<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Builds the canonical set of data collectors.
 *
 * Extracted so the DevToolbar orchestrator doesn't have to import every
 * concrete collector — keeping it focused on lifecycle and composition
 * rather than the collector inventory.
 */
final class CollectorFactory
{
    /**
     * @return array<string, CollectorInterface> keyed by each collector's getName()
     */
    public static function createDefault(): array
    {
        $collectors = [
            new RequestCollector(),
            QueryCollector::getInstance(),
            new MessageCollector(),
            ExceptionCollector::getInstance(),
            new HttpClientCollector(),
            new CacheCollector(),
            new TimelineCollector(),
            new HistoryCollector(), // Client-side only (localStorage)
        ];

        // getName() is the single source of truth for the registry key.
        $keyed = [];
        foreach ($collectors as $collector) {
            $keyed[$collector->getName()] = $collector;
        }

        return $keyed;
    }
}
