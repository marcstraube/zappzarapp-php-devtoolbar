<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

/**
 * Produces a {@see MiniBarConfig} from somewhere — cookies, headers,
 * static fixtures, etc.
 *
 * The renderer depends on the interface so tests can drive a deterministic
 * configuration without manipulating $_COOKIE.
 */
interface MiniBarConfigSource
{
    public function read(): MiniBarConfig;
}
