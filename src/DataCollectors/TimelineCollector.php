<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Timeline Collector
 *
 * Visual representation of request lifecycle with timing information
 */
class TimelineCollector implements CollectorInterface
{
    /**
     * @var array<string, array<string, mixed>>
     * @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PHP 8.4 hooks crash PDepend/PHPMD
     */
    private array $events = [];

    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PHP 8.4 hooks crash PDepend/PHPMD */
    private float $requestStart;

    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PHP 8.4 hooks crash PDepend/PHPMD */
    private bool $collecting = false;

    private bool $started    = false;

    /**
     * Start collecting timeline events
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->events     = [];
        // Collector start time, not $_SERVER['REQUEST_TIME_FLOAT']: in long-lived processes
        // (PHPUnit, queue workers) the SAPI request time is stale and inflates the leading
        // marker's duration to absorb idle time, breaking percentage/bottleneck calculations.
        $this->requestStart = microtime(true);
        $this->started      = true;

        // Add initial event
        $this->addEvent('request_start', 'Request Start', null, 'bootstrap');
    }

    /**
     * Stop collecting timeline events
     */
    public function stop(): void
    {
        if ($this->collecting) {
            $this->addEvent('request_end', 'Request End', null, 'response');
        }

        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'timeline';
    }

    public function reset(): void
    {
        $this->events     = [];
        $this->collecting = false;
        $this->started    = false;
    }

    public function getBadgeCount(): ?int
    {
        $count = count($this->buildTimeline());

        return $count > 0 ? $count : null;
    }

    /**
     * Get collected timeline data
     */
    public function getData(): array
    {
        $timeline = $this->buildTimeline();

        // total_time = sum of measured category durations (consistent with percentage/is_bottleneck).
        // Using wall-clock since requestStart would include uninstrumented idle time.
        $totalTime = array_sum(array_column($timeline, 'duration'));

        return [
            'timeline'   => $timeline,
            'total_time' => round($totalTime, 2),
            'events'     => $this->events,
            'count'      => count($timeline), // Number of timeline items for badge
        ];
    }

    /**
     * Add a timeline event
     *
     * @param string $key Event key (unique identifier)
     * @param string $label Event label (display name)
     * @param float|null $duration Optional explicit duration in ms
     * @param string $category Event category (bootstrap, middleware, controller, view, response)
     */
    public function addEvent(
        string $key,
        string $label,
        ?float $duration = null,
        string $category = 'other'
    ): void {
        if (!$this->collecting) {
            return;
        }

        $this->events[$key] = [
            'label'    => $label,
            'time'     => microtime(true),
            'duration' => $duration,
            'category' => $category,
        ];
    }

    /**
     * Mark start of a phase
     *
     * @param string $key Phase key
     * @param string $label Phase label
     * @param string $category Phase category
     */
    public function startPhase(string $key, string $label, string $category = 'other'): void
    {
        $this->addEvent($key . '_start', $label, null, $category);
    }

    /**
     * Mark end of a phase and calculate duration
     *
     * @param string $key Phase key (must match startPhase key)
     */
    public function endPhase(string $key): void
    {
        $startKey = $key . '_start';
        if (!isset($this->events[$startKey])) {
            return;
        }

        $startTime = $this->events[$startKey]['time'];
        $duration  = (microtime(true) - $startTime) * 1000; // Convert to ms

        // Update the start event with duration (label is left as-is — startPhase passes it through unchanged)
        $this->events[$startKey]['duration'] = round($duration, 2);
    }

    /**
     * Build timeline data from events
     *
     * @return array<int, array<string, mixed>> Timeline items
     */
    private function buildTimeline(): array
    {
        if ($this->events === []) {
            return [];
        }

        [$categoryData, $totalCategoryTime] = $this->aggregateByCategory();

        // Build timeline items — bottleneck/percentage relative to measured work time
        // (not wall-clock since requestStart, which would include uninstrumented idle time)
        $timeline = [];
        foreach ($categoryData as $category => $data) {
            $timeline[] = [
                'label'         => ucfirst((string) $category),
                'category'      => $category,
                'duration'      => round($data['time'], 2),
                'percentage'    => $totalCategoryTime > 0
                    ? round(($data['time'] / $totalCategoryTime) * 100, 1)
                    : 0,
                'events'        => $data['events'],
                'is_bottleneck' => $totalCategoryTime > 0
                    && ($data['time'] / $totalCategoryTime) > 0.5,
            ];
        }

        return $timeline;
    }

    /**
     * Group events by category and compute per-category durations
     *
     * @return array{0: array<string|int, array{time: float, events: array<int, array<string, mixed>>}>, 1: float}
     *               Tuple of [category data keyed by category, sum of measured work
     *               time]. Keys are string|int, not string: categories are
     *               consumer-supplied via addEvent(), and PHP stores a numeric
     *               string key (e.g. '404') as int — which is why the ucfirst()
     *               call above must keep its (string) cast.
     */
    private function aggregateByCategory(): array
    {
        $categoryData      = [];
        $totalCategoryTime = 0.0;

        foreach ($this->resolveEventDurations() as $event) {
            $category = $event['category'];
            $categoryData[$category] ??= ['time' => 0.0, 'events' => []];

            $categoryData[$category]['time'] += $event['duration'];
            $categoryData[$category]['events'][] = [
                'label'    => $event['label'],
                'duration' => round($event['duration'], 2),
            ];
        }

        // Drop categories without measurable time so the badge and the
        // percentage denominator stay consistent.
        foreach ($categoryData as $category => $data) {
            if ($data['time'] > 0) {
                $totalCategoryTime += $data['time'];
            } else {
                unset($categoryData[$category]);
            }
        }

        return [$categoryData, $totalCategoryTime];
    }

    /**
     * Resolve every event's duration in a single chronological pass.
     *
     * The events are walked in insertion (chronological) order with one
     * shared cursor. An explicit duration (set by endPhase() or
     * addAggregatedData()) wins and advances the cursor past the interval
     * it covers, so the following implicit event cannot re-count that span.
     * An implicit duration is the wall-clock gap since the previous event,
     * clamped at zero. Grouping by category only happens afterwards —
     * threading the cursor through category-grouped order (the previous
     * approach) produced negative durations and silently dropped whole
     * categories when events of different categories interleaved.
     *
     * @return array<int, array{label: string, category: string, duration: float}>
     */
    private function resolveEventDurations(): array
    {
        $prevTime = $this->requestStart;
        $resolved = [];

        foreach ($this->events as $event) {
            $explicit = $event['duration'];

            if ($explicit !== null) {
                $duration = (float) $explicit;
                $prevTime = max($prevTime, $event['time'] + $duration / 1000);
            } else {
                $duration = max(0.0, ($event['time'] - $prevTime) * 1000);
                $prevTime = $event['time'];
            }

            $resolved[] = [
                'label'    => $event['label'],
                'category' => $event['category'],
                'duration' => $duration,
            ];
        }

        return $resolved;
    }

    /**
     * Add aggregated data from other collectors
     *
     * @param string $label Label for the aggregated data
     * @param int $count Number of operations
     * @param float $totalTime Total time in milliseconds
     * @param string $category Category (e.g., 'database', 'http', 'cache')
     */
    public function addAggregatedData(
        string $label,
        int $count,
        float $totalTime,
        string $category = 'other'
    ): void {
        if (!$this->collecting || $count === 0) {
            return;
        }

        $key = 'aggregated_' . $category;
        $this->addEvent(
            $key,
            sprintf('%s (%d %s)', $label, $count, $count === 1 ? 'operation' : 'operations'),
            $totalTime,
            $category
        );
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }

    /**
     * Get request start time
     *
     * @return float Start timestamp
     */
    public function getRequestStart(): float
    {
        return $this->requestStart;
    }

    /**
     * Get elapsed time since request start
     *
     * @return float Elapsed time in milliseconds
     */
    public function getElapsedTime(): float
    {
        return ($this->started ? microtime(true) - $this->requestStart : 0) * 1000;
    }
}
