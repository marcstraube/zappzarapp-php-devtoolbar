<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;

/**
 * Test AbstractPanelRenderer's value-formatting helpers (time, memory, bytes).
 */
class AbstractPanelRendererFormattingTest extends TestCase
{
    private TestPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TestPanelRenderer();
    }

    // ========== formatTime() ==========

    public function testFormatTimeInMilliseconds(): void
    {
        $this->assertEquals('45.23ms', $this->renderer->publicFormatTime(45.23));
        $this->assertEquals('0.00ms', $this->renderer->publicFormatTime(0));
        $this->assertEquals('999.99ms', $this->renderer->publicFormatTime(999.99));
    }

    public function testFormatTimeInSeconds(): void
    {
        $this->assertEquals('1.00s', $this->renderer->publicFormatTime(1000));
        $this->assertEquals('1.50s', $this->renderer->publicFormatTime(1500));
        $this->assertEquals('123.45s', $this->renderer->publicFormatTime(123450));
    }

    public function testFormatTimeNegative(): void
    {
        $this->assertEquals('-10.00ms', $this->renderer->publicFormatTime(-10));
    }

    // ========== formatMemory() ==========

    public function testFormatMemoryInMegabytes(): void
    {
        $this->assertEquals('45.23MB', $this->renderer->publicFormatMemory(45.23));
        $this->assertEquals('0.00MB', $this->renderer->publicFormatMemory(0));
        $this->assertEquals('1023.99MB', $this->renderer->publicFormatMemory(1023.99));
    }

    public function testFormatMemoryInGigabytes(): void
    {
        $this->assertEquals('1.00GB', $this->renderer->publicFormatMemory(1024));
        $this->assertEquals('1.50GB', $this->renderer->publicFormatMemory(1536));
        $this->assertEquals('10.25GB', $this->renderer->publicFormatMemory(10496));
    }

    public function testFormatMemoryNegative(): void
    {
        $this->assertEquals('-10.00MB', $this->renderer->publicFormatMemory(-10));
    }

    // ========== formatBytes() ==========

    public function testFormatBytesInBytes(): void
    {
        $this->assertEquals('0B', $this->renderer->publicFormatBytes(0));
        $this->assertEquals('512B', $this->renderer->publicFormatBytes(512));
        $this->assertEquals('1023B', $this->renderer->publicFormatBytes(1023));
    }

    public function testFormatBytesInKilobytes(): void
    {
        $this->assertEquals('1.00KB', $this->renderer->publicFormatBytes(1024));
        $this->assertEquals('1.50KB', $this->renderer->publicFormatBytes(1536));
        $this->assertEquals('500.00KB', $this->renderer->publicFormatBytes(512000));
    }

    public function testFormatBytesInMegabytes(): void
    {
        $this->assertEquals('1.00MB', $this->renderer->publicFormatBytes(1048576));
        $this->assertEquals('2.50MB', $this->renderer->publicFormatBytes(2621440));
    }

    public function testFormatBytesInGigabytes(): void
    {
        $this->assertEquals('1.00GB', $this->renderer->publicFormatBytes(1073741824));
        $this->assertEquals('5.25GB', $this->renderer->publicFormatBytes(5637144576));
    }

    public function testFormatBytesInTerabytes(): void
    {
        $this->assertEquals('1.00TB', $this->renderer->publicFormatBytes(1099511627776));
        $this->assertEquals('2.50TB', $this->renderer->publicFormatBytes(2748779069440));
    }

    public function testFormatBytesNegative(): void
    {
        $this->assertEquals('0B', $this->renderer->publicFormatBytes(-1024));
    }

    public function testFormatBytesLarge(): void
    {
        // formatBytes() should cap at TB for arbitrarily large input.
        $result = $this->renderer->publicFormatBytes(PHP_INT_MAX);
        $this->assertStringEndsWith('TB', $result);
    }
}
