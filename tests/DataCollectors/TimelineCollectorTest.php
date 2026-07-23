<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\DataCollectors;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Zappzarapp\DevToolbar\DataCollectors\TimelineCollector;

/**
 * Test TimelineCollector timeline event tracking
 */
class TimelineCollectorTest extends TestCase
{
    private TimelineCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new TimelineCollector();
    }

    /**
     * Seed the collector with deterministic events so timing math can be
     * asserted exactly (addEvent() would stamp real microtime values).
     *
     * @param array<string, array{label: string, time: float, duration: float|null, category: string}> $events
     */
    private function seedEvents(float $requestStart, array $events): void
    {
        $reflection = new ReflectionClass($this->collector);
        $reflection->getProperty('requestStart')->setValue($this->collector, $requestStart);
        $reflection->getProperty('collecting')->setValue($this->collector, true);
        $reflection->getProperty('started')->setValue($this->collector, true);
        $reflection->getProperty('events')->setValue($this->collector, $events);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function timelineByCategory(): array
    {
        $byCategory = [];
        foreach ($this->collector->getData()['timeline'] as $item) {
            $byCategory[$item['category']] = $item;
        }

        return $byCategory;
    }

    public function testInterleavedCategoriesAreNeitherDroppedNorDoubleCounted(): void
    {
        // Regression: events of two categories interleave in time. The old
        // category-grouped cursor computed C's duration as C-A (double-
        // counting B's span) and B's as B-C (negative → whole category
        // silently dropped). Times are relative to requestStart.
        $start = 1_000.0;
        $this->seedEvents($start, [
            'a' => ['label' => 'A', 'time' => $start + 0.010, 'duration' => null, 'category' => 'cat1'],
            'b' => ['label' => 'B', 'time' => $start + 0.030, 'duration' => null, 'category' => 'cat2'],
            'c' => ['label' => 'C', 'time' => $start + 0.060, 'duration' => null, 'category' => 'cat1'],
        ]);

        $byCategory = $this->timelineByCategory();

        $this->assertArrayHasKey('cat2', $byCategory, 'interleaved category must not be dropped');
        $this->assertEqualsWithDelta(40.0, $byCategory['cat1']['duration'], 0.01); // 10ms (A) + 30ms (C)
        $this->assertEqualsWithDelta(20.0, $byCategory['cat2']['duration'], 0.01); // 20ms (B)
        $this->assertEqualsWithDelta(60.0, $this->collector->getData()['total_time'], 0.01);
    }

    public function testExplicitDurationDoesNotBleedIntoTheNextImplicitEvent(): void
    {
        // Regression: a phase carries an explicit duration covering
        // [time, time+duration]. The following implicit event must measure
        // only the gap after the phase, not re-count the phase interval.
        $start = 2_000.0;
        $this->seedEvents($start, [
            'p_start' => ['label' => 'Phase', 'time' => $start + 0.005, 'duration' => 50.0, 'category' => 'controller'],
            'render'  => ['label' => 'Render', 'time' => $start + 0.060, 'duration' => null, 'category' => 'view'],
        ]);

        $byCategory = $this->timelineByCategory();

        $this->assertEqualsWithDelta(50.0, $byCategory['controller']['duration'], 0.01);
        // 0.060 - (0.005 + 0.050) = 0.005s = 5ms, NOT 55ms.
        $this->assertEqualsWithDelta(5.0, $byCategory['view']['duration'], 0.01);
    }

    public function testStartRegistersRequestStartEvent(): void
    {
        $this->collector->start();

        $events = $this->collector->getData()['events'];

        $this->assertArrayHasKey('request_start', $events);
        $this->assertSame('Request Start', $events['request_start']['label']);
        $this->assertSame('bootstrap', $events['request_start']['category']);
        $this->assertNull($events['request_start']['duration']);
    }

    public function testStopRegistersRequestEndEvent(): void
    {
        $this->collector->start();
        $this->collector->stop();

        $events = $this->collector->getData()['events'];

        $this->assertArrayHasKey('request_end', $events);
        $this->assertSame('Request End', $events['request_end']['label']);
        $this->assertSame('response', $events['request_end']['category']);
    }

    public function testBadgeCountIsNullWithoutEvents(): void
    {
        $this->assertNull($this->collector->getBadgeCount());
    }

    public function testExactTimelineComputation(): void
    {
        // Explicit durations make every derived number deterministic:
        // alpha 10.004ms + beta 5.123ms → rounded item durations 10.0 + 5.12
        // sum to total 15.12; raw shares are 66.1% / 33.9%.
        $start = 3_000.0;
        $this->seedEvents($start, [
            'a1' => ['label' => 'A1', 'time' => $start + 0.001, 'duration' => 10.004, 'category' => 'alpha'],
            'b1' => ['label' => 'B1', 'time' => $start + 0.020, 'duration' => 5.123, 'category' => 'beta'],
        ]);

        $data       = $this->collector->getData();
        $byCategory = $this->timelineByCategory();

        $this->assertEqualsWithDelta(15.12, $data['total_time'], 0.0001);
        $this->assertSame(2, $data['count']);
        $this->assertSame(2, $this->collector->getBadgeCount());

        $this->assertSame('Alpha', $byCategory['alpha']['label']);
        $this->assertEqualsWithDelta(10.0, $byCategory['alpha']['duration'], 0.001);
        $this->assertEqualsWithDelta(66.1, $byCategory['alpha']['percentage'], 0.001);
        $this->assertTrue($byCategory['alpha']['is_bottleneck']);

        $this->assertEqualsWithDelta(5.12, $byCategory['beta']['duration'], 0.001);
        $this->assertEqualsWithDelta(33.9, $byCategory['beta']['percentage'], 0.001);
        $this->assertFalse($byCategory['beta']['is_bottleneck']);

        $this->assertCount(1, $byCategory['beta']['events']);
        $this->assertSame('B1', $byCategory['beta']['events'][0]['label']);
        $this->assertEqualsWithDelta(5.12, $byCategory['beta']['events'][0]['duration'], 0.0001);
    }

    public function testEqualFiftyPercentSplitIsNotBottleneck(): void
    {
        // Bottleneck requires strictly MORE than half the measured time.
        $start = 4_000.0;
        $this->seedEvents($start, [
            'a' => ['label' => 'A', 'time' => $start + 0.001, 'duration' => 10.0, 'category' => 'alpha'],
            'b' => ['label' => 'B', 'time' => $start + 0.020, 'duration' => 10.0, 'category' => 'beta'],
        ]);

        $byCategory = $this->timelineByCategory();

        $this->assertEqualsWithDelta(50.0, $byCategory['alpha']['percentage'], 0.001);
        $this->assertFalse($byCategory['alpha']['is_bottleneck']);
        $this->assertFalse($byCategory['beta']['is_bottleneck']);
    }

    public function testZeroDurationCategoryIsDropped(): void
    {
        // Categories without measurable time must not appear in the timeline
        // (they would skew the badge count while contributing 0%).
        $start = 5_000.0;
        $this->seedEvents($start, [
            'ghost' => ['label' => 'Ghost', 'time' => $start + 0.001, 'duration' => 0.0, 'category' => 'ghost'],
            'real'  => ['label' => 'Real', 'time' => $start + 0.010, 'duration' => 8.0, 'category' => 'real'],
        ]);

        $data       = $this->collector->getData();
        $byCategory = $this->timelineByCategory();

        $this->assertArrayNotHasKey('ghost', $byCategory);
        $this->assertSame(1, $data['count']);
        $this->assertEqualsWithDelta(100.0, $byCategory['real']['percentage'], 0.001);
    }

    public function testSubMillisecondImplicitGapIsNotInflated(): void
    {
        // An implicit duration is the raw gap since the previous event —
        // clamped at zero, never padded up to a full millisecond.
        $start = 6_000.0;
        $this->seedEvents($start, [
            'tiny' => ['label' => 'Tiny', 'time' => $start + 0.0005, 'duration' => null, 'category' => 'tiny'],
        ]);

        $byCategory = $this->timelineByCategory();

        $this->assertEqualsWithDelta(0.5, $byCategory['tiny']['duration'], 0.0001);
    }

    public function testEndPhaseComputesDurationInMilliseconds(): void
    {
        // A phase started 1000 seconds ago must resolve to ~1,000,000 ms; the
        // 100ms tolerance absorbs test runtime but exposes any off-by-one on
        // the s→ms factor (999/1001 would be a full second off).
        $now = microtime(true);
        $this->seedEvents($now - 1_001.0, [
            'p_start' => ['label' => 'P', 'time' => $now - 1_000.0, 'duration' => null, 'category' => 'controller'],
        ]);

        $this->collector->endPhase('p');

        $duration = $this->collector->getData()['events']['p_start']['duration'];
        $this->assertEqualsWithDelta(1_000_000.0, $duration, 100.0);
    }

    public function testElapsedTimeMeasuresMillisecondsSinceRequestStart(): void
    {
        $reflection = new ReflectionClass($this->collector);
        $reflection->getProperty('started')->setValue($this->collector, true);
        $reflection->getProperty('requestStart')->setValue($this->collector, microtime(true) - 1_000.0);

        $this->assertEqualsWithDelta(1_000_000.0, $this->collector->getElapsedTime(), 100.0);
    }

    public function testElapsedTimeIsZeroBeforeStart(): void
    {
        $this->assertSame(0.0, $this->collector->getElapsedTime());
    }

    public function testAggregatedDataLabelUsesSingularAndPlural(): void
    {
        $this->collector->start();
        $this->collector->addAggregatedData('Queries', 1, 5.0, 'database');
        $this->collector->addAggregatedData('Calls', 3, 2.0, 'http');
        $this->collector->stop();

        $events = $this->collector->getData()['events'];

        $this->assertSame('Queries (1 operation)', $events['aggregated_database']['label']);
        $this->assertSame('Calls (3 operations)', $events['aggregated_http']['label']);
    }

    public function testStartStopCollecting(): void
    {
        $this->assertFalse($this->collector->isCollecting());

        $this->collector->start();
        $this->assertTrue($this->collector->isCollecting());

        $this->collector->stop();
        $this->assertFalse($this->collector->isCollecting());
    }

    public function testGetName(): void
    {
        $this->assertEquals('timeline', $this->collector->getName());
    }

    public function testTracksEvents(): void
    {
        $this->collector->start();
        $this->collector->addEvent('bootstrap', 'Bootstrap', 15.0, 'bootstrap');
        $this->collector->addEvent('middleware', 'Middleware', 8.0, 'middleware');
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('timeline', $data);
        $this->assertArrayHasKey('total_time', $data);
        $this->assertGreaterThan(0, $data['total_time']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->addEvent('test', 'Test Event', 10.0);

        $data = $this->collector->getData();

        $this->assertEmpty($data['events']);
    }

    public function testStartPhaseAndEndPhase(): void
    {
        $this->collector->start();

        $this->collector->startPhase('controller', 'Controller', 'controller');
        usleep(1000); // 1ms delay
        $this->collector->endPhase('controller');

        $this->collector->stop();

        $data   = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayHasKey('controller_start', $events);
        $this->assertNotNull($events['controller_start']['duration']);
        $this->assertGreaterThan(0, $events['controller_start']['duration']);
    }

    public function testAddAggregatedData(): void
    {
        $this->collector->start();

        $this->collector->addAggregatedData(
            'Database Queries',
            10,
            150.5,
            'database'
        );

        $this->collector->stop();

        $data   = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayHasKey('aggregated_database', $events);
        $this->assertEquals(150.5, $events['aggregated_database']['duration']);
    }

    public function testDoesNotAddAggregatedDataWithZeroCount(): void
    {
        $this->collector->start();

        $this->collector->addAggregatedData(
            'No Operations',
            0,
            0.0,
            'none'
        );

        $this->collector->stop();

        $data   = $this->collector->getData();
        $events = $data['events'];

        $this->assertArrayNotHasKey('aggregated_none', $events);
    }

    public function testGetRequestStart(): void
    {
        $this->collector->start();

        $requestStart = $this->collector->getRequestStart();

        $this->assertGreaterThan(0, $requestStart);
    }

    public function testGetElapsedTime(): void
    {
        $this->collector->start();

        usleep(1000); // 1ms delay

        $elapsed = $this->collector->getElapsedTime();

        $this->assertGreaterThan(0, $elapsed);
    }

    public function testBuildsTimelineWithCategories(): void
    {
        $this->collector->start();

        $this->collector->addEvent('bootstrap', 'Bootstrap', 15.0, 'bootstrap');
        $this->collector->addEvent('middleware', 'Middleware', 8.0, 'middleware');
        $this->collector->addEvent('controller', 'Controller', 100.0, 'controller');

        $this->collector->stop();

        $data     = $this->collector->getData();
        $timeline = $data['timeline'];

        $this->assertIsArray($timeline);
        $this->assertNotEmpty($timeline);

        // Check that timeline items have required fields
        foreach ($timeline as $item) {
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('duration', $item);
            $this->assertArrayHasKey('percentage', $item);
            $this->assertArrayHasKey('is_bottleneck', $item);
        }
    }

    public function testDetectsBottleneck(): void
    {
        $this->collector->start();

        // Add one event that takes >50% of time
        $this->collector->addEvent('fast', 'Fast', 10.0);
        $this->collector->addEvent('slow', 'Slow', 200.0, 'controller');

        $this->collector->stop();

        $data     = $this->collector->getData();
        $timeline = $data['timeline'];

        // Find the controller category
        $controllerItem = null;
        foreach ($timeline as $item) {
            if ($item['category'] === 'controller') {
                $controllerItem = $item;
                break;
            }
        }

        $this->assertNotNull($controllerItem);
        $this->assertTrue($controllerItem['is_bottleneck']);
    }
}
