<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Interface for all data collectors
 *
 * Data collectors gather debugging information during request lifecycle.
 */
interface CollectorInterface
{
    /**
     * Start collecting data
     */
    public function start(): void;

    /**
     * Stop collecting data
     */
    public function stop(): void;

    /**
     * Clear all collected state.
     *
     * Called between requests in long-running runtimes (Swoole, RoadRunner,
     * queue workers) so state never bleeds across requests.
     */
    public function reset(): void;

    /**
     * Get the panel payload for this collector.
     *
     * The array shape is specific to this collector's panel renderer; it is
     * not a stable cross-collector contract. Use {@see getBadgeCount()} for
     * the tab badge rather than reading a key out of this array.
     *
     * @return array<string, mixed> Collected data
     */
    public function getData(): array;

    /**
     * Get collector name.
     *
     * The single source of truth for the collector's registry key (tab id,
     * panel-renderer lookup) and its tab label.
     *
     * @return string Collector name
     */
    public function getName(): string;

    /**
     * Number to show in the tab badge, or null for no badge.
     *
     * Return 0 to force an always-visible zero badge (e.g. a client-side
     * populated tab), a positive int to show a count, or null to suppress
     * the badge entirely.
     */
    public function getBadgeCount(): ?int;
}
