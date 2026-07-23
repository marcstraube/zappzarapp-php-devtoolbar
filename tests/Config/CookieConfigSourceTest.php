<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Config;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Config\CookieConfigSource;
use Zappzarapp\DevToolbar\Config\CookieKey;
use Zappzarapp\DevToolbar\Config\Filter\HexColorFilter;
use Zappzarapp\DevToolbar\Config\GitBranchResolver;
use Zappzarapp\DevToolbar\Config\MiniBarConfig;
use Zappzarapp\DevToolbar\Config\MiniBarLabel;

class CookieConfigSourceTest extends TestCase
{
    /**
     * @param array<string, string> $cookies
     */
    private function makeSource(array $cookies, ?string $branch = null): CookieConfigSource
    {
        return new CookieConfigSource(
            cookies: $cookies,
            gitResolver: new GitBranchResolver('/nonexistent/' . uniqid(), $branch),
            colorFilter: new HexColorFilter(),
        );
    }

    /**
     * Encode a value as a URL-encoded JSON cookie payload.
     *
     * Wraps the json_encode|urlencode pair so PHPStan doesn't complain
     * about json_encode's false return type — every fixture in this file
     * passes a known-encodable value.
     */
    private function cookie(mixed $value): string
    {
        return urlencode((string) json_encode($value));
    }

    public function testEmptyCookiesReturnDefaults(): void
    {
        $config = $this->makeSource([])->read();

        $this->assertSame([MiniBarLabel::Branding], $config->labels);
        $this->assertSame(MiniBarConfig::DEFAULT_BRANCH_COLORS, $config->branchColors);
        $this->assertNull($config->thresholds);
        $this->assertNull($config->gitBranch);
    }

    public function testParsesValidLabels(): void
    {
        $cookies = [
            CookieKey::Labels->value => $this->cookie(['branch', 'route']),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame([MiniBarLabel::Branch, MiniBarLabel::Route], $config->labels);
    }

    public function testFiltersUnknownLabelTypes(): void
    {
        $cookies = [
            CookieKey::Labels->value => $this->cookie(['branch', 'evil-label', 'route']),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame([MiniBarLabel::Branch, MiniBarLabel::Route], $config->labels);
    }

    public function testFallsBackToDefaultLabelsWhenAllInvalid(): void
    {
        $cookies = [
            CookieKey::Labels->value => $this->cookie(['evil-label-1', 'evil-label-2']),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame([MiniBarLabel::Branding], $config->labels);
    }

    public function testIgnoresMalformedLabelJson(): void
    {
        $cookies = [
            CookieKey::Labels->value => 'not-valid-json-{[',
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame([MiniBarLabel::Branding], $config->labels);
    }

    public function testParsesValidBranchColors(): void
    {
        $cookies = [
            CookieKey::Colors->value => $this->cookie([
                'feat' => '#0000ff',
                'fix'  => '#FFAA00',
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame('#0000ff', $config->branchColors['feat']);
        $this->assertSame('#ffaa00', $config->branchColors['fix']); // sanitized to lowercase
        // unmodified defaults remain
        $this->assertSame(MiniBarConfig::DEFAULT_BRANCH_COLORS['default'], $config->branchColors['default']);
    }

    public function testRejectsUnknownColorKeys(): void
    {
        $cookies = [
            CookieKey::Colors->value => $this->cookie([
                'evil' => '#000000',
                'feat' => '#0000ff',
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertArrayNotHasKey('evil', $config->branchColors);
        $this->assertSame('#0000ff', $config->branchColors['feat']);
    }

    public function testRejectsInvalidColorValues(): void
    {
        $cookies = [
            CookieKey::Colors->value => $this->cookie([
                'feat'  => '#fff',                    // shorthand, rejected
                'fix'   => 'red',                     // named, rejected
                'chore' => '#000;background:url(x)', // injection attempt, rejected
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        // All rejected → defaults preserved
        $this->assertSame(MiniBarConfig::DEFAULT_BRANCH_COLORS['feat'], $config->branchColors['feat']);
        $this->assertSame(MiniBarConfig::DEFAULT_BRANCH_COLORS['fix'], $config->branchColors['fix']);
        $this->assertSame(MiniBarConfig::DEFAULT_BRANCH_COLORS['chore'], $config->branchColors['chore']);
    }

    public function testParsesValidThresholds(): void
    {
        $cookies = [
            CookieKey::Thresholds->value => $this->cookie([
                'time_ms'   => 2000,
                'memory_mb' => 100,
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame(['time_ms' => 2000, 'memory_mb' => 100], $config->thresholds);
    }

    public function testRejectsUnknownThresholdKeys(): void
    {
        $cookies = [
            CookieKey::Thresholds->value => $this->cookie([
                'evil_key' => 100,
                'time_ms'  => 2000,
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame(['time_ms' => 2000], $config->thresholds);
    }

    public function testRejectsNonPositiveThresholdValues(): void
    {
        $cookies = [
            CookieKey::Thresholds->value => $this->cookie([
                'time_ms'    => 0,
                'memory_mb'  => -10,
                'http_count' => 5,
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame(['http_count' => 5], $config->thresholds);
    }

    public function testRejectsNonIntThresholdValues(): void
    {
        $cookies = [
            CookieKey::Thresholds->value => $this->cookie([
                'time_ms'    => '2000',
                'memory_mb'  => 50.5,
                'http_count' => 5,
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertSame(['http_count' => 5], $config->thresholds);
    }

    public function testReturnsNullThresholdsWhenAllInvalid(): void
    {
        $cookies = [
            CookieKey::Thresholds->value => $this->cookie([
                'evil_key' => 100,
                'time_ms'  => -1,
            ]),
        ];

        $config = $this->makeSource($cookies)->read();

        $this->assertNull($config->thresholds);
    }

    public function testIncludesGitBranchFromResolver(): void
    {
        $config = $this->makeSource([], 'feature/test')->read();

        $this->assertSame('feature/test', $config->gitBranch);
    }
}
