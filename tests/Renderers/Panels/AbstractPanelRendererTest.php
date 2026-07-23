<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers\Panels;

use PHPUnit\Framework\TestCase;

/**
 * Test AbstractPanelRenderer's HTML helpers — escaping, sections, empty
 * states, key/value tables, and the performance-class threshold mapper.
 *
 * Value-formatting helpers (formatTime, formatMemory, formatBytes) are
 * covered separately by AbstractPanelRendererFormattingTest.
 */
class AbstractPanelRendererTest extends TestCase
{
    private TestPanelRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TestPanelRenderer();
    }

    // ========== escapeHtml() ==========

    public function testEscapeHtmlBasic(): void
    {
        $this->assertEquals('Hello World', $this->renderer->publicEscapeHtml('Hello World'));
    }

    public function testEscapeHtmlSpecialChars(): void
    {
        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $this->renderer->publicEscapeHtml('<script>alert("XSS")</script>'));
        $this->assertEquals('Tom &amp; Jerry', $this->renderer->publicEscapeHtml('Tom & Jerry'));
        $this->assertEquals('Price: 5 &lt; 10', $this->renderer->publicEscapeHtml('Price: 5 < 10'));
    }

    public function testEscapeHtmlQuotes(): void
    {
        $this->assertEquals('It&apos;s working', $this->renderer->publicEscapeHtml("It's working"));
        $this->assertEquals('&quot;Quoted text&quot;', $this->renderer->publicEscapeHtml('"Quoted text"'));
    }

    public function testEscapeHtmlEmpty(): void
    {
        $this->assertEquals('', $this->renderer->publicEscapeHtml(''));
    }

    // ========== renderSection() ==========

    public function testRenderSection(): void
    {
        $result = $this->renderer->publicRenderSection('My Title', '<p>Content</p>');

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-section-title', $result);
        $this->assertStringContainsString('My Title', $result);
        $this->assertStringContainsString('<p>Content</p>', $result);
    }

    public function testRenderSectionEscapesTitle(): void
    {
        $result = $this->renderer->publicRenderSection('<script>alert("XSS")</script>', '<p>Safe</p>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderSectionDoesNotEscapeContent(): void
    {
        $result = $this->renderer->publicRenderSection('Title', '<strong>Bold</strong>');

        $this->assertStringContainsString('<strong>Bold</strong>', $result);
    }

    // ========== renderEmptyState() ==========

    public function testRenderEmptyState(): void
    {
        $result = $this->renderer->publicRenderEmptyState('No data available');

        $this->assertStringContainsString('<p>', $result);
        $this->assertStringContainsString('No data available', $result);
    }

    public function testRenderEmptyStateEscapesMessage(): void
    {
        $result = $this->renderer->publicRenderEmptyState('<script>alert("XSS")</script>');

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    // ========== renderKeyValueTable() ==========

    public function testRenderKeyValueTableEmpty(): void
    {
        $result = $this->renderer->publicRenderKeyValueTable([]);

        $this->assertEquals('', $result);
    }

    public function testRenderKeyValueTableSimple(): void
    {
        $data = [
            'name' => 'John',
            'age'  => '25',
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('dev-toolbar-kv-table', $result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('John', $result);
        $this->assertStringContainsString('age', $result);
        $this->assertStringContainsString('25', $result);
    }

    public function testRenderKeyValueTableEscapesValues(): void
    {
        $data = [
            'title' => '<script>alert("XSS")</script>',
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString(htmlspecialchars('<script>'), $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderKeyValueTableArrayValue(): void
    {
        $data = [
            'items' => ['apple', 'banana', 'orange'],
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('items', $result);
        $this->assertStringContainsString('apple', $result);
        $this->assertStringContainsString('banana', $result);
        $this->assertStringContainsString('orange', $result);
    }

    public function testRenderKeyValueTableIntegerValue(): void
    {
        $data = [
            'count' => 42,
        ];

        $result = $this->renderer->publicRenderKeyValueTable($data);

        $this->assertStringContainsString('count', $result);
        $this->assertStringContainsString('42', $result);
    }

    // ========== getPerformanceClass() ==========

    public function testGetPerformanceClassFast(): void
    {
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(50, 100, 500));
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(0, 100, 500));
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(99.99, 100, 500));
    }

    public function testGetPerformanceClassSlow(): void
    {
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(100, 100, 500));
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(250, 100, 500));
        $this->assertEquals('slow', $this->renderer->publicGetPerformanceClass(499.99, 100, 500));
    }

    public function testGetPerformanceClassVerySlow(): void
    {
        $this->assertEquals('very-slow', $this->renderer->publicGetPerformanceClass(500, 100, 500));
        $this->assertEquals('very-slow', $this->renderer->publicGetPerformanceClass(1000, 100, 500));
    }

    public function testGetPerformanceClassNegativeValue(): void
    {
        $this->assertEquals('fast', $this->renderer->publicGetPerformanceClass(-10, 100, 500));
    }
}
