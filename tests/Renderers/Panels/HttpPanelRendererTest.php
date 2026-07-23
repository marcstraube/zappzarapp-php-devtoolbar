<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\HttpPanelRenderer;

/**
 * Test HttpPanelRenderer
 */
class HttpPanelRendererTest extends TestCase
{
    private HttpPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new HttpPanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('http', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        $this->assertStringContainsString('No HTTP requests made', $result);
    }

    public function testRenderTabWithZeroCount(): void
    {
        $data = [
            'count'      => 0,
            'requests'   => [],
            'total_time' => 0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No HTTP requests made', $result);
    }

    public function testRenderTabWithSingleRequest(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 123.45,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 123.45,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('HTTP Client (1 request, 123.45ms total)', $result);
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('https://api.example.com/users', $result);
        $this->assertStringContainsString('Status: 200', $result);
        $this->assertStringContainsString('(123.45ms)', $result);
        $this->assertStringContainsString('🟢', $result); // good performance icon
    }

    public function testRenderTabWithMultipleRequests(): void
    {
        $data = [
            'count'      => 3,
            'total_time' => 450.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 150.0,
                    'performance_level' => 'good',
                ],
                [
                    'method'            => 'POST',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 201,
                    'time'              => 200.0,
                    'performance_level' => 'warning',
                ],
                [
                    'method'            => 'DELETE',
                    'url'               => 'https://api.example.com/users/1',
                    'status'            => 204,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('HTTP Client (3 requests, 450.00ms total)', $result);
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('POST', $result);
        $this->assertStringContainsString('DELETE', $result);
    }

    public function testRenderTabWithDifferentHttpMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $data = [
                'count'      => 1,
                'total_time' => 100.0,
                'requests'   => [
                    [
                        'method'            => $method,
                        'url'               => 'https://api.example.com/resource',
                        'status'            => 200,
                        'time'              => 100.0,
                        'performance_level' => 'good',
                    ],
                ],
            ];

            $result = $this->renderer->renderTab($data);

            $this->assertStringContainsString($method, $result);
        }
    }

    public function testRenderTabWithDifferentStatusCodes(): void
    {
        $statusCodes = [
            200, // OK
            201, // Created
            301, // Moved Permanently
            302, // Found
            400, // Bad Request
            404, // Not Found
            500, // Internal Server Error
            503, // Service Unavailable
        ];

        foreach ($statusCodes as $status) {
            $data = [
                'count'      => 1,
                'total_time' => 100.0,
                'requests'   => [
                    [
                        'method'            => 'GET',
                        'url'               => 'https://api.example.com/resource',
                        'status'            => $status,
                        'time'              => 100.0,
                        'performance_level' => 'good',
                    ],
                ],
            ];

            $result = $this->renderer->renderTab($data);

            $this->assertStringContainsString('Status: ' . $status, $result);
        }
    }

    public function testRenderTabWithGoodPerformanceLevel(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 50.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 50.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('🟢', $result);
        $this->assertStringContainsString('good', $result);
    }

    public function testRenderTabWithWarningPerformanceLevel(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 800.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 800.0,
                    'performance_level' => 'warning',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('🟡', $result);
        $this->assertStringContainsString('warning', $result);
    }

    public function testRenderTabWithCriticalPerformanceLevel(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 2000.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 2000.0,
                    'performance_level' => 'critical',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('🔴', $result);
        $this->assertStringContainsString('critical', $result);
    }

    public function testRenderTabWithUnknownPerformanceLevel(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'unknown',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('⚪', $result);
    }

    public function testRenderTabEscapesHtmlInUrl(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/search?q=<script>alert("XSS")</script>',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInMethod(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => '<script>alert("XSS")</script>',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabWithBacktrace(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                    'backtrace'         => [
                        [
                            'file' => '/var/www/src/Service/UserService.php',
                            'line' => 42,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Called at:', $result);
        $this->assertStringContainsString('/var/www/src/Service/UserService.php', $result);
        $this->assertStringContainsString('42', $result);
    }

    public function testRenderTabWithoutBacktrace(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringNotContainsString('Called at:', $result);
    }

    public function testRenderTabEscapesHtmlInBacktraceFile(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                    'backtrace'         => [
                        [
                            'file' => '<script>alert("XSS")</script>',
                            'line' => 42,
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabContainsExpectedSections(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 100.0,
            'requests'   => [
                [
                    'method'            => 'GET',
                    'url'               => 'https://api.example.com/users',
                    'status'            => 200,
                    'time'              => 100.0,
                    'performance_level' => 'good',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-http-request', $result);
        $this->assertStringContainsString('dev-toolbar-http-header', $result);
        $this->assertStringContainsString('dev-toolbar-http-status', $result);
        $this->assertStringContainsString('dev-toolbar-http-time', $result);
    }

    public function testRenderTabHandlesMissingRequestFields(): void
    {
        $data = [
            'count'      => 1,
            'total_time' => 0,
            'requests'   => [
                [], // Empty request data
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Should render with defaults
        $this->assertStringContainsString('GET', $result);
        $this->assertStringContainsString('Status: 0', $result);
        $this->assertStringContainsString('(0.00ms)', $result);
    }
}
