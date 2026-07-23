<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Guard;
use Zappzarapp\DevToolbar\Config\ToolbarConfig;

/**
 * Static convenience facade over the default {@see EnvironmentGuard}.
 *
 * Kept for the common front-controller pattern
 * (`if (DevToolbarGuard::isEnabled()) { ... }`). For dependency injection or
 * a custom enablement policy, implement {@see GuardInterface} and pass it via
 * {@see ToolbarConfig} instead.
 */
class DevToolbarGuard
{
    /**
     * Check if the Developer Toolbar should be enabled for this request.
     */
    public static function isEnabled(): bool
    {
        return (new EnvironmentGuard())->isEnabled();
    }
}
