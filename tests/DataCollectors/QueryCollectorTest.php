<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\DataCollectors;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\DataCollectors\QueryCollector;

/**
 * Test QueryCollector query tracking
 */
class QueryCollectorTest extends TestCase
{
    private QueryCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = QueryCollector::getInstance();
        $this->collector->reset();
    }

    public function testGetInstance(): void
    {
        $instance1 = QueryCollector::getInstance();
        $instance2 = QueryCollector::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testTracksQueries(): void
    {
        $this->collector->start();
        $this->collector->trackQuery(
            'SELECT * FROM users WHERE id = ?',
            [1],
            45.67
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(45.67, $data['total_time']);
        $this->assertCount(1, $data['queries']);

        $query = $data['queries'][0];
        $this->assertEquals('SELECT * FROM users WHERE id = ?', $query['sql']);
        $this->assertEquals([1], $query['bindings']);
        $this->assertEquals(45.67, $query['time']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->trackQuery('SELECT 1', [], 10.0);

        $data = $this->collector->getData();

        $this->assertEquals(0, $data['count']);
    }

    public function testTracksMultipleQueries(): void
    {
        $this->collector->start();
        $this->collector->trackQuery('SELECT * FROM users', [], 25.5);
        $this->collector->trackQuery('SELECT * FROM posts', [], 30.2);
        $this->collector->trackQuery('SELECT * FROM comments', [], 15.1);
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(3, $data['count']);
        $this->assertEquals(70.8, $data['total_time']);
    }

    public function testGetName(): void
    {
        $this->assertEquals('queries', $this->collector->getName());
    }

    public function testReset(): void
    {
        $this->collector->start();
        $this->collector->trackQuery('SELECT 1', [], 10.0);
        $this->collector->reset();

        $data = $this->collector->getData();

        $this->assertEquals(0, $data['count']);
    }
}
