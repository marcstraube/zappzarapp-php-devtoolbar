<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Utils;

use Random\RandomException;

/**
 * Request Utilities
 *
 * Helper functions for request ID generation, time formatting, and status display.
 */
class RequestUtils
{
    /**
     * Generate a unique request ID
     *
     * @return string Unique request ID
     * @throws RandomException If secure random source is unavailable
     */
    public static function generateId(): string
    {
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get formatted time ago string
     *
     * @param int $timestamp Unix timestamp
     * @return string Time ago string (e.g., "5s ago", "2m ago")
     */
    public static function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return $diff . 's ago';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }

        return floor($diff / 86400) . 'd ago';
    }

    /**
     * Get status code color/icon
     *
     * @param int $statusCode HTTP status code
     * @return array<string, string> Color and icon
     */
    public static function getStatusDisplay(int $statusCode): array
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['color' => 'green', 'icon' => '🟢'];
        }

        if ($statusCode >= 300 && $statusCode < 400) {
            return ['color' => 'blue', 'icon' => '🔵'];
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return ['color' => 'yellow', 'icon' => '🟡'];
        }

        return ['color' => 'red', 'icon' => '🔴'];
    }
}
