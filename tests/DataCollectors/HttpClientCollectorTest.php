<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\DataCollectors;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\DataCollectors\HttpClientCollector;

/**
 * Test HttpClientCollector HTTP request tracking
 */
class HttpClientCollectorTest extends TestCase
{
    private HttpClientCollector $collector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = new HttpClientCollector();
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
        $this->assertEquals('http', $this->collector->getName());
    }

    public function testTracksHttpRequests(): void
    {
        $this->collector->start();
        $this->collector->trackRequest(
            'GET',
            'https://api.example.com/users',
            127.5,
            200,
            ['Content-Type' => 'application/json'],
            '{"users": []}'
        );
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(1, $data['count']);
        $this->assertEquals(127.5, $data['total_time']);
        $this->assertCount(1, $data['requests']);

        $request = $data['requests'][0];
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('https://api.example.com/users', $request['url']);
        $this->assertEquals(127.5, $request['time']);
        $this->assertEquals(200, $request['status']);
        $this->assertEquals('good', $request['performance_level']);
    }

    public function testDoesNotTrackWhenNotCollecting(): void
    {
        // Don't call start()
        $this->collector->trackRequest('GET', 'https://example.com', 50, 200);

        $data = $this->collector->getData();

        $this->assertEquals(0, $data['count']);
    }

    public function testTracksMultipleRequests(): void
    {
        $this->collector->start();
        $this->collector->trackRequest('GET', 'https://api1.com', 100, 200);
        $this->collector->trackRequest('POST', 'https://api2.com', 150, 201);
        $this->collector->trackRequest('DELETE', 'https://api3.com', 50, 204);
        $this->collector->stop();

        $data = $this->collector->getData();

        $this->assertEquals(3, $data['count']);
        $this->assertEquals(300, $data['total_time']);
    }

    public function testPerformanceLevel(): void
    {
        $this->collector->start();

        // Good performance (< 200ms)
        $this->collector->trackRequest('GET', 'https://fast.com', 150, 200);

        // Warning performance (200-500ms)
        $this->collector->trackRequest('GET', 'https://medium.com', 300, 200);

        // Critical performance (> 500ms)
        $this->collector->trackRequest('GET', 'https://slow.com', 600, 200);

        $this->collector->stop();

        $data     = $this->collector->getData();
        $requests = $data['requests'];

        $this->assertEquals('good', $requests[0]['performance_level']);
        $this->assertEquals('warning', $requests[1]['performance_level']);
        $this->assertEquals('critical', $requests[2]['performance_level']);
    }

    public function testFiltersSensitiveData(): void
    {
        $this->collector->start();

        $requestBody = '{"email":"test@example.com","password":"secret123","token":"abc123"}';
        $this->collector->trackRequest(
            'POST',
            'https://api.com/login',
            100,
            200,
            [],
            $requestBody
        );

        $this->collector->stop();

        $data    = $this->collector->getData();
        $request = $data['requests'][0];

        // Check that sensitive data is filtered
        $this->assertStringContainsString('[FILTERED]', $request['body']);
        $this->assertStringNotContainsString('secret123', $request['body']);
    }

    public function testTruncatesLongResponses(): void
    {
        $this->collector->start();

        // Create a response longer than 10000 characters
        $longResponse = str_repeat('a', 10500);
        $this->collector->trackRequest(
            'GET',
            'https://api.com/large',
            100,
            200,
            [],
            $longResponse
        );

        $this->collector->stop();

        $data    = $this->collector->getData();
        $request = $data['requests'][0];

        // Should be truncated with size info
        $this->assertStringContainsString('(truncated,', $request['body']);
        $this->assertStringContainsString('bytes total)', $request['body']);
        $this->assertLessThan(strlen($longResponse), strlen((string) $request['body']));
    }

    public function testCapturesBacktrace(): void
    {
        $this->collector->start();
        $this->collector->trackRequest('GET', 'https://api.com', 50, 200);
        $this->collector->stop();

        $data    = $this->collector->getData();
        $request = $data['requests'][0];

        $this->assertIsArray($request['backtrace']);
    }

    /**
     * Regression: a failing fetch leaves $http_response_header undefined, so
     * the collector must not pass null into its array-typed header parsers.
     * Before the fix that raised a TypeError under strict_types, turning a
     * recoverable false into a fatal error in the instrumented app.
     */
    public function testWrapFileGetContentsSurvivesAFailedRequest(): void
    {
        $this->collector->start();

        // Non-existent local path: file_get_contents() returns false and
        // never populates $http_response_header (the crash scenario). Swallow
        // the expected E_WARNING; the point is that no TypeError escapes.
        set_error_handler(static fn (): bool => true);
        try {
            $result = $this->collector->wrapFileGetContents(
                __DIR__ . '/does-not-exist-' . uniqid() . '.txt'
            );
        } finally {
            restore_error_handler();
        }

        $this->collector->stop();

        $this->assertFalse($result);

        $data = $this->collector->getData();
        $this->assertSame(1, $data['count']);
        $this->assertSame(0, $data['requests'][0]['status']);
        $this->assertSame([], $data['requests'][0]['headers']);
    }
}
