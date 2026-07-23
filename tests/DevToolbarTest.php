<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zappzarapp\DevToolbar\Config\ToolbarConfig;
use Zappzarapp\DevToolbar\DataCollectors\CollectorInterface;
use Zappzarapp\DevToolbar\DevToolbar;
use Zappzarapp\DevToolbar\Guard\GuardInterface;

/**
 * Test the DevToolbar composition root: lifecycle, guard integration,
 * collector registration and reset.
 */
class DevToolbarTest extends TestCase
{
    private function toolbar(bool $enabled, SpyCollector $spy): DevToolbar
    {
        return new DevToolbar(new ToolbarConfig(
            collectors: [$spy->getName() => $spy],
            guard: new FixedGuard($enabled),
        ));
    }

    public function testBootStartsCollectorsWhenGuardEnabled(): void
    {
        $spy     = new SpyCollector();
        $toolbar = $this->toolbar(true, $spy);

        $toolbar->boot();

        $this->assertTrue($toolbar->isBooted());
        $this->assertSame(1, $spy->startCalls);
    }

    public function testBootIsNoopWhenGuardDisabled(): void
    {
        $spy     = new SpyCollector();
        $toolbar = $this->toolbar(false, $spy);

        $toolbar->boot();

        $this->assertFalse($toolbar->isBooted());
        $this->assertSame(0, $spy->startCalls);
    }

    public function testInjectStopsCollectorsAndInjectsToolbar(): void
    {
        $spy     = new SpyCollector();
        $toolbar = $this->toolbar(true, $spy);
        $toolbar->boot();

        $result = $toolbar->injectToolbar('<html><body></body></html>');

        $this->assertSame(1, $spy->stopCalls);
        $this->assertStringContainsString('dev-toolbar-mini', $result);
        // A collector without a dedicated panel still gets a tab (fallback).
        $this->assertStringContainsString('data-tab="spy"', $result);
    }

    public function testInjectReturnsBufferUnchangedWhenDisabled(): void
    {
        $spy     = new SpyCollector();
        $toolbar = $this->toolbar(false, $spy);

        $this->assertSame('<body></body>', $toolbar->injectToolbar('<body></body>'));
    }

    public function testResetClearsCollectorsAndUnboots(): void
    {
        $spy     = new SpyCollector();
        $toolbar = $this->toolbar(true, $spy);
        $toolbar->boot();

        $toolbar->reset();

        $this->assertFalse($toolbar->isBooted());
        $this->assertSame(1, $spy->resetCalls);
    }

    public function testAddCollectorRegistersByName(): void
    {
        $spy     = new SpyCollector();
        $toolbar = new DevToolbar(new ToolbarConfig(collectors: []));

        $toolbar->addCollector($spy);

        $this->assertSame($spy, $toolbar->getCollector('spy'));
        $this->assertNull($toolbar->getCollector('missing'));
    }

    /**
     * @param list<string> $headers
     */
    #[DataProvider('contentTypeProvider')]
    public function testInjectsOnlyIntoHtmlResponses(array $headers, bool $expected): void
    {
        $toolbar = new DevToolbar(new ToolbarConfig(collectors: []));
        $method  = new ReflectionMethod(DevToolbar::class, 'isHtmlResponse');

        $this->assertSame($expected, $method->invoke($toolbar, $headers));
    }

    /**
     * @return array<string, array{0: list<string>, 1: bool}>
     */
    public static function contentTypeProvider(): array
    {
        return [
            'no content-type (PHP default is html)' => [[], true],
            'text/html with charset'                => [['Content-Type: text/html; charset=utf-8'], true],
            'case-insensitive'                      => [['X-Foo: bar', 'content-type: TEXT/HTML'], true],
            'application/json'                      => [['Content-Type: application/json'], false],
            'text/plain'                            => [['Content-Type: text/plain'], false],
        ];
    }
}

/**
 * Fixed enablement policy for exercising both guard branches deterministically
 * (the default EnvironmentGuard always disables under the CLI SAPI).
 */
final readonly class FixedGuard implements GuardInterface
{
    public function __construct(private bool $enabled)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}

/**
 * Records lifecycle calls so the orchestrator's behavior can be asserted.
 */
final class SpyCollector implements CollectorInterface
{
    public int $startCalls = 0;

    public int $stopCalls = 0;

    public int $resetCalls = 0;

    public function start(): void
    {
        $this->startCalls++;
    }

    public function stop(): void
    {
        $this->stopCalls++;
    }

    public function reset(): void
    {
        $this->resetCalls++;
    }

    public function getData(): array
    {
        return ['sample' => 'value'];
    }

    public function getName(): string
    {
        return 'spy';
    }

    public function getBadgeCount(): ?int
    {
        return null;
    }
}
