<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Guard;
use Zappzarapp\DevToolbar\Config\ToolbarConfig;

/**
 * Decides whether the Developer Toolbar should run for the current request.
 *
 * Consumers can supply their own implementation (IP allowlist, framework
 * debug flag, feature toggle, …) and hand it to the toolbar via
 * {@see ToolbarConfig}. The default is
 * {@see EnvironmentGuard}, a fail-closed environment check.
 */
interface GuardInterface
{
    /**
     * @return bool True if the toolbar should be enabled for this request
     */
    public function isEnabled(): bool;
}
