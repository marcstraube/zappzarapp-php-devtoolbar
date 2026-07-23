<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\DataCollectors;

use __PHP_Incomplete_Class;
use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\DataCollectors\CacheCollector;

/**
 * Test CacheCollector cache operation tracking
 */
class CacheCollectorTest extends TestCase
{
    private CacheCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new CacheCollector();
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
        $this->assertEquals('cache', $this->collector->getName());
    }

    public function testTracksCacheHit(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'get',
            'user:123',
            2.5,
            '{"id":123,"name":"John"}'
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(1, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals(100.0, $data['hit_rate']);
        $this->assertEquals(2.5, $data['total_time']);
    }

    public function testTracksCacheMiss(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'get',
            'user:999',
            1.8
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(1, $data['misses']);
        $this->assertEquals(0.0, $data['hit_rate']);
    }

    public function testCalculatesHitRate(): void
    {
        $this->collector->start();

        // 3 hits
        $this->collector->trackOperation('get', 'key1', 1.0, 'value1');
        $this->collector->trackOperation('get', 'key2', 1.0, 'value2');
        $this->collector->trackOperation('get', 'key3', 1.0, 'value3');

        // 1 miss
        $this->collector->trackOperation('get', 'key4', 1.0);

        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(4, $data['count']);
        $this->assertEquals(3, $data['hits']);
        $this->assertEquals(1, $data['misses']);
        $this->assertEquals(75.0, $data['hit_rate']); // 3/4 = 75%
    }

    public function testTracksSetOperation(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'set',
            'session:abc',
            3.2,
            ['user_id' => 123],
            7200
        );
        $this->collector->stop();

        $data      = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertEquals('set', $operation['type']);
        $this->assertEquals('session:abc', $operation['key']);
        $this->assertEquals(3.2, $operation['time']);
        $this->assertEquals(7200, $operation['ttl']);
        $this->assertArrayHasKey('size', $operation);
    }

    public function testTracksDeleteOperation(): void
    {
        $this->collector->start();
        $this->collector->trackOperation(
            'delete',
            'old_cache:*',
            5.5,
            15 // Number of keys deleted
        );
        $this->collector->stop();

        $data      = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertEquals('delete', $operation['type']);
        $this->assertEquals('old_cache:*', $operation['key']);
        $this->assertEquals(5.5, $operation['time']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->trackOperation('get', 'key', 1.0, 'value');

        $data = $this->collector->getData();

        $this->assertEquals(0, $data['count']);
    }

    public function testTracksMultipleOperations(): void
    {
        $this->collector->start();

        $this->collector->trackOperation('get', 'key1', 1.0, 'value1');
        $this->collector->trackOperation('set', 'key2', 2.0, 'value2', 3600);
        $this->collector->trackOperation('delete', 'key3', 1.5, 1);

        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(3, $data['count']);
        $this->assertEquals(4.5, $data['total_time']);
        $this->assertCount(3, $data['operations']);
    }

    public function testFiltersSensitiveValues(): void
    {
        $this->collector->start();

        $sensitiveData = ['password' => 'secret', 'token' => 'abc123', 'name' => 'John'];
        $this->collector->trackOperation('get', 'user:1', 1.0, $sensitiveData);

        $this->collector->stop();

        $data      = $this->collector->getData();
        $operation = $data['operations'][0];
        $value     = $operation['value'];

        $this->assertEquals('[FILTERED]', $value['password']);
        $this->assertEquals('[FILTERED]', $value['token']);
        $this->assertEquals('John', $value['name']); // Not filtered
    }

    public function testCapturesBacktrace(): void
    {
        $this->collector->start();
        $this->collector->trackOperation('get', 'key', 1.0, 'value');
        $this->collector->stop();

        $data      = $this->collector->getData();
        $operation = $data['operations'][0];

        $this->assertIsArray($operation['backtrace']);
    }

    /**
     * Regression test: JSON strings should not cause unserialize errors
     *
     * Bug: CacheCollector::filterValue() tried to unserialize all strings,
     * which caused "unserialize(): Error at offset 0 of 27 bytes" for JSON.
     *
     * This prevented demoCacheOperations() from completing, which blocked
     * demoTimeline() from running, causing timeline to show only 2 entries.
     *
     * Fix: Check for serialized format markers before attempting unserialize.
     */
    public function testJsonStringsDoNotCauseUnserializeErrors(): void
    {
        $this->collector->start();

        // These JSON strings should NOT cause errors
        $jsonValues = [
            '{"id": 123, "name": "John"}',
            '{"token": "secret123", "expires": 3600}',
            '{"user_id": 123, "last_active": 1234567890}',
            '[1, 2, 3, 4, 5]',
            '"simple string"',
            'null',
            'true',
            '42',
        ];

        foreach ($jsonValues as $index => $json) {
            // Should not throw unserialize errors
            $this->collector->trackOperation(
                'get',
                'test:key:' . $index,
                2.0,
                $json
            );
        }

        $this->collector->stop();

        // All operations should be tracked successfully
        $data = $this->collector->getData();
        $this->assertSame(count($jsonValues), $data['count']);
        $this->assertSame(count($jsonValues), $data['hits']);
        $this->assertSame(0, $data['misses']);
    }

    /**
     * Test that actual PHP serialized data IS unserialized correctly
     */
    public function testPhpSerializedDataIsUnserialized(): void
    {
        $this->collector->start();

        $phpArray   = ['id' => 123, 'name' => 'John'];
        $serialized = serialize($phpArray);

        $this->collector->trackOperation('get', 'test:key', 2.0, $serialized);

        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertSame(1, $data['count']);
        $this->assertCount(1, $data['operations']);

        // The value should have been unserialized and filtered
        $operation = $data['operations'][0];
        $this->assertIsArray($operation['value']);
        $this->assertArrayHasKey('id', $operation['value']);
        $this->assertArrayHasKey('name', $operation['value']);
    }

    /**
     * Security regression: a serialized object in a cache value must never
     * be instantiated. safeUnserialize() passes allowed_classes => false, so
     * an 'O:'-prefixed payload (which isSerializedString() feeds into
     * unserialize) decodes to __PHP_Incomplete_Class and no __wakeup() or
     * __destruct() gadget can fire. This guards against PHP object injection
     * from attacker-influenced cache strings.
     */
    public function testSerializedObjectsAreNotInstantiated(): void
    {
        CacheObjectInjectionProbe::$instantiated = false;

        $this->collector->start();
        $this->collector->trackOperation(
            'get',
            'attacker:key',
            1.0,
            serialize(new CacheObjectInjectionProbe())
        );
        $this->collector->stop();

        $this->assertFalse(
            CacheObjectInjectionProbe::$instantiated,
            'unserialize() must not instantiate classes from cache values'
        );

        $value = $this->collector->getData()['operations'][0]['value'];
        $this->assertInstanceOf(__PHP_Incomplete_Class::class, $value);
    }
}

/**
 * Probe object whose deserialization would flip a flag — used to prove the
 * collector never instantiates classes from cache payloads.
 */
class CacheObjectInjectionProbe
{
    public static bool $instantiated = false;

    public function __wakeup(): void
    {
        self::$instantiated = true;
    }
}

