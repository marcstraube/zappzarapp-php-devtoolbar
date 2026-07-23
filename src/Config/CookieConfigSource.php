<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

use Zappzarapp\DevToolbar\Config\Filter\HexColorFilter;

/**
 * Builds a {@see MiniBarConfig} from request cookies and the git working copy.
 *
 * Treats every cookie value as untrusted input: each is JSON-decoded and then
 * strictly validated (whitelist enums, hex-color regex, integer ranges) before
 * any value reaches the renderer. Invalid values are silently dropped in favor
 * of defaults — the toolbar should never break a request because someone
 * tampered with their own cookie.
 *
 * This class is the only place in the DevToolbar that reads $_COOKIE / the
 * environment, making it the single audit point for untrusted input handling.
 */
final readonly class CookieConfigSource implements MiniBarConfigSource
{
    /**
     * Allowed threshold keys — values from a cookie outside this set are dropped.
     * Mirrors PerformanceAnalyzer::DEFAULT_THRESHOLDS keys.
     */
    private const array ALLOWED_THRESHOLD_KEYS = [
        'time_ms',
        'memory_mb',
        'query_count',
        'query_time_ms',
        'http_count',
        'http_time_ms',
    ];

    /**
     * @param array<string, string> $cookies      Raw cookie values (typically $_COOKIE)
     * @param GitBranchResolver     $gitResolver  Resolves the current branch name
     * @param HexColorFilter        $colorFilter  Validates branch color values
     */
    public function __construct(
        private array $cookies,
        private GitBranchResolver $gitResolver,
        private HexColorFilter $colorFilter,
    ) {
    }

    public static function fromGlobals(): self
    {
        /** @var array<string, string> $cookies */
        $cookies = $_COOKIE;

        return new self(
            cookies: $cookies,
            gitResolver: GitBranchResolver::fromGlobals(),
            colorFilter: new HexColorFilter(),
        );
    }

    public function read(): MiniBarConfig
    {
        return new MiniBarConfig(
            labels: $this->parseLabels($this->cookies[CookieKey::Labels->value] ?? null),
            branchColors: $this->parseBranchColors($this->cookies[CookieKey::Colors->value] ?? null),
            thresholds: $this->parseThresholds($this->cookies[CookieKey::Thresholds->value] ?? null),
            gitBranch: $this->gitResolver->resolve(),
        );
    }

    /**
     * @return list<MiniBarLabel>
     */
    private function parseLabels(?string $raw): array
    {
        $decoded = $this->decodeJson($raw);
        if (!is_array($decoded) || $decoded === []) {
            return [MiniBarLabel::Branding];
        }

        $labels = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                continue;
            }

            $label = MiniBarLabel::tryFrom($value);
            if ($label !== null) {
                $labels[] = $label;
            }
        }

        return $labels === [] ? [MiniBarLabel::Branding] : $labels;
    }

    /**
     * @return array<string, string>
     */
    private function parseBranchColors(?string $raw): array
    {
        $decoded = $this->decodeJson($raw);
        if (!is_array($decoded)) {
            return MiniBarConfig::DEFAULT_BRANCH_COLORS;
        }

        $validated = MiniBarConfig::DEFAULT_BRANCH_COLORS;
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (!array_key_exists($key, MiniBarConfig::DEFAULT_BRANCH_COLORS)) {
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            if (!$this->colorFilter->isSafe($value)) {
                continue;
            }

            $validated[$key] = $this->colorFilter->sanitize($value);
        }

        return $validated;
    }

    /**
     * @return array<string, int>|null
     */
    private function parseThresholds(?string $raw): ?array
    {
        $decoded = $this->decodeJson($raw);
        if (!is_array($decoded)) {
            return null;
        }

        $validated = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (!in_array($key, self::ALLOWED_THRESHOLD_KEYS, true)) {
                continue;
            }

            if (!is_int($value)) {
                continue;
            }

            if ($value <= 0) {
                continue;
            }

            $validated[$key] = $value;
        }

        return $validated === [] ? null : $validated;
    }

    private function decodeJson(?string $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode(urldecode($raw), associative: true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
