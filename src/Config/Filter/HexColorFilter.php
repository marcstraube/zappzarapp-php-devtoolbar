<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config\Filter;

use Zappzarapp\Security\Sanitization\InputFilter;

/**
 * Validates a CSS hex color in 6-digit form (e.g. "#3b82f6").
 *
 * Rejects shorthand (#fff), 8-digit (#rrggbbaa), named colors, and any
 * non-hex content. Sanitize returns the lowercased canonical form or an
 * empty string for invalid input — never partial / unsafe output that
 * could land in a CSS context.
 */
final readonly class HexColorFilter implements InputFilter
{
    private const string PATTERN = '/^#[0-9a-f]{6}$/i';

    public function isSafe(string $input): bool
    {
        return preg_match(self::PATTERN, $input) === 1;
    }

    public function sanitize(string $input): string
    {
        return $this->isSafe($input) ? strtolower($input) : '';
    }
}
