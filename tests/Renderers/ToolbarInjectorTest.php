<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Renderers;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Config\CookieConfigSource;
use Zappzarapp\DevToolbar\DataCollectors\CollectorFactory;
use Zappzarapp\DevToolbar\Renderers\Panels\PanelRendererFactory;
use Zappzarapp\DevToolbar\Renderers\ToolbarInjector;
use Zappzarapp\Security\Csp\Nonce\NonceRegistry;

/**
 * Test ToolbarInjector output-buffer injection.
 */
class ToolbarInjectorTest extends TestCase
{
    // Rendered exactly once per injection by MiniBarRenderer, and (unlike the
    // data-payload marker) not echoed inside the JS bundle.
    private const string MARKER = '<div class="dev-toolbar-mini">';

    private function injector(): ToolbarInjector
    {
        return new ToolbarInjector(
            CollectorFactory::createDefault(),
            PanelRendererFactory::createDefault(),
            CookieConfigSource::fromGlobals(),
            NonceRegistry::generator(),
        );
    }

    public function testReturnsOutputUnchangedWithoutABodyTag(): void
    {
        $output = '<div>no body here</div>';

        $this->assertSame($output, $this->injector()->inject($output));
    }

    public function testInjectsExactlyOnceBeforeTheLastBody(): void
    {
        // The literal "</body>" appears twice; only the final (real) one
        // should receive the toolbar.
        $output = '<html><body><p>a </body> in text</p></body></html>';

        $result = $this->injector()->inject($output);

        $this->assertSame(1, substr_count($result, self::MARKER), 'toolbar must be injected exactly once');
        $this->assertLessThan(
            strripos($result, '</body>'),
            strpos($result, self::MARKER),
            'toolbar must sit before the last </body>'
        );
        $this->assertStringEndsWith('</body></html>', $result);
    }

    public function testMatchesTheClosingTagCaseInsensitively(): void
    {
        $result = $this->injector()->inject('<body>x</BODY>');

        $this->assertStringContainsString(self::MARKER, $result);
        $this->assertLessThan(strripos($result, '</body>'), strpos($result, self::MARKER));
    }
}
