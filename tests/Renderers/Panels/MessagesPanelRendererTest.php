<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\MessagesPanelRenderer;

/**
 * Test MessagesPanelRenderer
 */
class MessagesPanelRendererTest extends TestCase
{
    private MessagesPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MessagesPanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('messages', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        $this->assertStringContainsString('No log messages', $result);
    }

    public function testRenderTabWithNoMessages(): void
    {
        $data = [
            'messages' => [],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No log messages', $result);
    }

    public function testRenderTabWithSingleMessage(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'info',
                    'level_name' => 'INFO',
                    'message'    => 'Application started',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-message', $result);
        $this->assertStringContainsString('info', $result);
        $this->assertStringContainsString('INFO', $result);
        $this->assertStringContainsString('Application started', $result);
        $this->assertStringContainsString('2024-01-15 10:30:45', $result);
    }

    public function testRenderTabWithMultipleMessages(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'debug',
                    'level_name' => 'DEBUG',
                    'message'    => 'Debug message',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
                [
                    'level'      => 'info',
                    'level_name' => 'INFO',
                    'message'    => 'Info message',
                    'datetime'   => '2024-01-15 10:30:46',
                ],
                [
                    'level'      => 'warning',
                    'level_name' => 'WARNING',
                    'message'    => 'Warning message',
                    'datetime'   => '2024-01-15 10:30:47',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Check all messages are rendered
        $this->assertStringContainsString('DEBUG', $result);
        $this->assertStringContainsString('Debug message', $result);
        $this->assertStringContainsString('INFO', $result);
        $this->assertStringContainsString('Info message', $result);
        $this->assertStringContainsString('WARNING', $result);
        $this->assertStringContainsString('Warning message', $result);

        // Check level classes
        $this->assertStringContainsString('class="dev-toolbar-message debug"', $result);
        $this->assertStringContainsString('class="dev-toolbar-message info"', $result);
        $this->assertStringContainsString('class="dev-toolbar-message warning"', $result);
    }

    public function testRenderTabWithErrorLevel(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'error',
                    'level_name' => 'ERROR',
                    'message'    => 'Database connection failed',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('class="dev-toolbar-message error"', $result);
        $this->assertStringContainsString('ERROR', $result);
        $this->assertStringContainsString('Database connection failed', $result);
    }

    public function testRenderTabEscapesHtmlInMessage(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'info',
                    'level_name' => 'INFO',
                    'message'    => '<script>alert("XSS")</script>',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInLevelName(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'info',
                    'level_name' => '<script>XSS</script>',
                    'message'    => 'Test message',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>XSS', $result);
    }

    public function testRenderTabEscapesHtmlInDatetime(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'info',
                    'level_name' => 'INFO',
                    'message'    => 'Test message',
                    'datetime'   => '<script>XSS</script>',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>XSS', $result);
    }

    public function testRenderTabWithMissingFields(): void
    {
        $data = [
            'messages' => [
                [], // Empty message array
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Should render with default values
        $this->assertStringContainsString('dev-toolbar-message', $result);
        $this->assertStringContainsString('info', $result); // Default level
        $this->assertStringContainsString('INFO', $result); // Default level_name
    }

    public function testRenderTabStructure(): void
    {
        $data = [
            'messages' => [
                [
                    'level'      => 'info',
                    'level_name' => 'INFO',
                    'message'    => 'Test',
                    'datetime'   => '2024-01-15 10:30:45',
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Verify expected HTML structure
        $this->assertStringContainsString('dev-toolbar-message', $result);
        $this->assertStringContainsString('dev-toolbar-message-header', $result);
        $this->assertStringContainsString('dev-toolbar-message-level', $result);
        $this->assertStringContainsString('dev-toolbar-message-time', $result);
        $this->assertStringContainsString('dev-toolbar-message-text', $result);
    }

    public function testRenderTabWithAllLogLevels(): void
    {
        $data = [
            'messages' => [
                ['level' => 'debug', 'level_name' => 'DEBUG', 'message' => 'Debug', 'datetime' => '2024-01-15 10:30:45'],
                ['level' => 'info', 'level_name' => 'INFO', 'message' => 'Info', 'datetime' => '2024-01-15 10:30:46'],
                ['level' => 'notice', 'level_name' => 'NOTICE', 'message' => 'Notice', 'datetime' => '2024-01-15 10:30:47'],
                ['level' => 'warning', 'level_name' => 'WARNING', 'message' => 'Warning', 'datetime' => '2024-01-15 10:30:48'],
                ['level' => 'error', 'level_name' => 'ERROR', 'message' => 'Error', 'datetime' => '2024-01-15 10:30:49'],
                ['level' => 'critical', 'level_name' => 'CRITICAL', 'message' => 'Critical', 'datetime' => '2024-01-15 10:30:50'],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $levels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];
        foreach ($levels as $level) {
            $this->assertStringContainsString($level, $result);
        }
    }
}
