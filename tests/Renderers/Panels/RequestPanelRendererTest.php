<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\RequestPanelRenderer;

/**
 * Test RequestPanelRenderer
 */
class RequestPanelRendererTest extends TestCase
{
    private RequestPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new RequestPanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('request', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        // Should render with default values
        $this->assertStringContainsString('Current Request', $result);
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('/', $result);
        $this->assertStringContainsString('200', $result);
        $this->assertStringContainsString('0.00ms', $result);
        $this->assertStringContainsString('0.00MB', $result);
    }

    public function testRenderTabWithTypicalRequestData(): void
    {
        $data = [
            'method'         => 'POST',
            'uri'            => '/api/users',
            'status_code'    => 201,
            'execution_time' => 45.67,
            'memory_peak'    => 8.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('POST', $result);
        $this->assertStringContainsString('/api/users', $result);
        $this->assertStringContainsString('201', $result);
        $this->assertStringContainsString('45.67ms', $result);
        $this->assertStringContainsString('8.50MB', $result);
    }

    public function testRenderTabEscapesHtmlInMethod(): void
    {
        $data = [
            'method' => '<script>alert("XSS")</script>',
        ];

        $result = $this->renderer->renderTab($data);

        $expectedEscaped = htmlspecialchars('<script>');
        $this->assertStringContainsString($expectedEscaped, $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInUri(): void
    {
        $data = [
            'uri' => '/api?name=<script>alert("XSS")</script>',
        ];

        $result = $this->renderer->renderTab($data);

        $expectedEscaped = htmlspecialchars('<script>');
        $this->assertStringContainsString($expectedEscaped, $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabWithHeaders(): void
    {
        $data = [
            'method'  => 'GET',
            'uri'     => '/',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'Mozilla/5.0',
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Headers', $result);
        $this->assertStringContainsString('Content-Type', $result);
        $this->assertStringContainsString('application/json', $result);
        $this->assertStringContainsString('Accept', $result);
        $this->assertStringContainsString('User-Agent', $result);
        $this->assertStringContainsString('Mozilla/5.0', $result);
    }

    public function testRenderTabWithArrayHeader(): void
    {
        $data = [
            'headers' => [
                'X-Custom' => ['value1', 'value2'],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('X-Custom', $result);
        $this->assertStringContainsString('value1', $result);
        $this->assertStringContainsString('value2', $result);
    }

    public function testRenderTabEscapesHtmlInHeaders(): void
    {
        $data = [
            'headers' => [
                'X-Dangerous' => '<script>alert("XSS")</script>',
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $expectedEscaped = htmlspecialchars('<script>');
        $this->assertStringContainsString($expectedEscaped, $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabWithoutHeaders(): void
    {
        $data = [
            'method' => 'GET',
            'uri'    => '/',
        ];

        $result = $this->renderer->renderTab($data);

        // Headers section should not be rendered
        $this->assertStringNotContainsString('Headers', $result);
    }

    public function testRenderTabContainsExpectedSections(): void
    {
        $result = $this->renderer->renderTab([]);

        // Verify structure contains expected elements
        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-kv-table', $result);
        $this->assertStringContainsString('Current Request', $result);
    }

    public function testRenderTabStatusCodeTypes(): void
    {
        $statusCodes = [200, 201, 301, 404, 500];

        foreach ($statusCodes as $code) {
            $data   = ['status_code' => $code];
            $result = $this->renderer->renderTab($data);
            $this->assertStringContainsString((string)$code, $result);
        }
    }
}
