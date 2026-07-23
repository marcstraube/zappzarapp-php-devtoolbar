<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

/**
 * Cookie names used for client-side DevToolbar configuration.
 *
 * Centralized to keep raw cookie strings out of business logic and to
 * make audits trivial: every superglobal access goes through these cases.
 */
enum CookieKey: string
{
    case Labels     = 'devbar_labels';
    case Colors     = 'devbar_colors';
    case Thresholds = 'devbar_thresholds';
}
