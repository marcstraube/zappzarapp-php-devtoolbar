<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

/**
 * Whitelist of label types that may appear in the mini bar.
 *
 * Backed by string for cookie serialization. Any value not matching a case
 * is rejected by tryFrom() — manipulated cookies cannot inject unknown labels.
 */
enum MiniBarLabel: string
{
    case Branding  = 'branding';
    case Branch    = 'branch';
    case Route     = 'route';
    case RequestId = 'request-id';
}
