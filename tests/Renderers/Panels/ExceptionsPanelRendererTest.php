<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Renderers\Panels\ExceptionsPanelRenderer;

/**
 * Test ExceptionsPanelRenderer
 */
class ExceptionsPanelRendererTest extends TestCase
{
    private ExceptionsPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new ExceptionsPanelRenderer();
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('exceptions', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests ==========

    public function testRenderTabWithEmptyData(): void
    {
        $result = $this->renderer->renderTab([]);

        $this->assertStringContainsString('No exceptions thrown', $result);
    }

    public function testRenderTabWithNoExceptions(): void
    {
        $data = [
            'exceptions' => [],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No exceptions thrown', $result);
    }

    public function testRenderTabWithSingleHandledException(): void
    {
        $data = [
            'exceptions' => [
                [
                    'handled' => true,
                    'class'   => 'RuntimeException',
                    'message' => 'Something went wrong',
                    'file'    => '/var/www/src/Controller.php',
                    'line'    => 42,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-exception', $result);
        $this->assertStringContainsString('handled', $result);
        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('Something went wrong', $result);
        $this->assertStringContainsString('/var/www/src/Controller.php', $result);
        $this->assertStringContainsString('42', $result);
    }

    public function testRenderTabWithUnhandledException(): void
    {
        $data = [
            'exceptions' => [
                [
                    'handled' => false,
                    'class'   => 'FatalErrorException',
                    'message' => 'Fatal error occurred',
                    'file'    => '/var/www/src/Fatal.php',
                    'line'    => 123,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-exception', $result);
        // When handled is false, class should NOT contain 'handled'
        $this->assertStringNotContainsString('class="dev-toolbar-exception handled"', $result);
        $this->assertStringContainsString('FatalErrorException', $result);
        $this->assertStringContainsString('Fatal error occurred', $result);
    }

    public function testRenderTabWithMultipleExceptions(): void
    {
        $data = [
            'exceptions' => [
                [
                    'handled' => true,
                    'class'   => 'InvalidArgumentException',
                    'message' => 'Invalid argument provided',
                    'file'    => '/var/www/src/Validator.php',
                    'line'    => 50,
                ],
                [
                    'handled' => true,
                    'class'   => 'LogicException',
                    'message' => 'Logic error detected',
                    'file'    => '/var/www/src/Service.php',
                    'line'    => 75,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Check both exceptions are rendered
        $this->assertStringContainsString('InvalidArgumentException', $result);
        $this->assertStringContainsString('Invalid argument provided', $result);
        $this->assertStringContainsString('LogicException', $result);
        $this->assertStringContainsString('Logic error detected', $result);
        $this->assertStringContainsString('/var/www/src/Validator.php:50', $result);
        $this->assertStringContainsString('/var/www/src/Service.php:75', $result);
    }

    public function testRenderTabEscapesHtmlInExceptionClass(): void
    {
        $data = [
            'exceptions' => [
                [
                    'class'   => '<script>alert("XSS")</script>',
                    'message' => 'Test',
                    'file'    => '/test.php',
                    'line'    => 1,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInExceptionMessage(): void
    {
        $data = [
            'exceptions' => [
                [
                    'class'   => 'Exception',
                    'message' => '<script>alert("XSS")</script>',
                    'file'    => '/test.php',
                    'line'    => 1,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInExceptionFile(): void
    {
        $data = [
            'exceptions' => [
                [
                    'class'   => 'Exception',
                    'message' => 'Test',
                    'file'    => '<script>alert("XSS")</script>',
                    'line'    => 1,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabWithMissingFields(): void
    {
        $data = [
            'exceptions' => [
                [], // Empty exception array
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Should render with default values
        $this->assertStringContainsString('dev-toolbar-exception', $result);
        $this->assertStringContainsString('Exception', $result); // Default class
        $this->assertStringContainsString(':0', $result); // Default line
    }

    public function testRenderTabWithDefaultHandledValue(): void
    {
        $data = [
            'exceptions' => [
                [
                    // 'handled' key is missing, should default to true
                    'class'   => 'TestException',
                    'message' => 'Test',
                    'file'    => '/test.php',
                    'line'    => 10,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Should have 'handled' class by default
        $this->assertStringContainsString('class="dev-toolbar-exception handled"', $result);
    }

    public function testRenderTabStructure(): void
    {
        $data = [
            'exceptions' => [
                [
                    'handled' => true,
                    'class'   => 'TestException',
                    'message' => 'Test message',
                    'file'    => '/test.php',
                    'line'    => 42,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Verify expected HTML structure
        $this->assertStringContainsString('dev-toolbar-exception', $result);
        $this->assertStringContainsString('dev-toolbar-exception-class', $result);
        $this->assertStringContainsString('dev-toolbar-exception-message', $result);
        $this->assertStringContainsString('dev-toolbar-exception-location', $result);
    }

    public function testRenderTabWithCommonExceptionTypes(): void
    {
        $exceptionTypes = [
            'Exception',
            'RuntimeException',
            'InvalidArgumentException',
            'LogicException',
            'DomainException',
            'OutOfBoundsException',
        ];

        foreach ($exceptionTypes as $exceptionType) {
            $data = [
                'exceptions' => [
                    [
                        'class'   => $exceptionType,
                        'message' => 'Test',
                        'file'    => '/test.php',
                        'line'    => 1,
                    ],
                ],
            ];

            $result = $this->renderer->renderTab($data);
            $this->assertStringContainsString($exceptionType, $result);
        }
    }

    public function testRenderTabWithLongFilePath(): void
    {
        $data = [
            'exceptions' => [
                [
                    'class'   => 'Exception',
                    'message' => 'Test',
                    'file'    => '/very/long/path/to/some/deeply/nested/directory/structure/that/contains/a/file.php',
                    'line'    => 999,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Full path should be rendered
        $this->assertStringContainsString('/very/long/path/to/some/deeply/nested/directory/structure/that/contains/a/file.php', $result);
        $this->assertStringContainsString('999', $result);
    }

    public function testRenderTabWithZeroLine(): void
    {
        $data = [
            'exceptions' => [
                [
                    'class'   => 'Exception',
                    'message' => 'Test',
                    'file'    => '/test.php',
                    'line'    => 0,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString(':0', $result);
    }

    public function testRenderTabMixedHandledAndUnhandled(): void
    {
        $data = [
            'exceptions' => [
                [
                    'handled' => true,
                    'class'   => 'HandledException',
                    'message' => 'Handled exception',
                    'file'    => '/test1.php',
                    'line'    => 10,
                ],
                [
                    'handled' => false,
                    'class'   => 'UnhandledException',
                    'message' => 'Unhandled exception',
                    'file'    => '/test2.php',
                    'line'    => 20,
                ],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        // Both should be rendered with different classes
        $this->assertStringContainsString('HandledException', $result);
        $this->assertStringContainsString('UnhandledException', $result);
        // Count occurrences of 'handled' class - should find it once
        $this->assertEquals(1, substr_count($result, 'class="dev-toolbar-exception handled"'));
    }
}
