<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

/**
 * Immutable mini bar configuration.
 *
 * Resolved once per request from cookies, environment, and the git
 * working copy by a {@see MiniBarConfigSource}. The renderer reads from
 * an instance of this class instead of touching superglobals — keeping
 * rendering pure and testable.
 */
final readonly class MiniBarConfig
{
    public const array DEFAULT_BRANCH_COLORS = [
        'feat'    => '#3b82f6',
        'fix'     => '#f59e0b',
        'hotfix'  => '#ef4444',
        'chore'   => '#6b7280',
        'default' => '#10b981',
    ];

    /**
     * @param list<MiniBarLabel>          $labels        Active labels in display order
     * @param array<string, string>       $branchColors  Branch type → hex color (always includes defaults)
     * @param array<string, int>|null     $thresholds    Custom performance thresholds; null = use analyzer defaults
     * @param string|null                 $gitBranch     Resolved branch name; null when not on a branch
     */
    public function __construct(
        public array $labels,
        public array $branchColors,
        public ?array $thresholds,
        public ?string $gitBranch,
    ) {
    }

    public static function default(): self
    {
        return new self(
            labels: [MiniBarLabel::Branding],
            branchColors: self::DEFAULT_BRANCH_COLORS,
            thresholds: null,
            gitBranch: null,
        );
    }
}
