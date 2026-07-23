<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Provides backtrace capture for DataCollectors
 *
 * Captures the first relevant call frame outside of DevToolbar internals,
 * normalizing file paths relative to the project root.
 */
trait BacktraceTrait
{
    /**
     * Get the first relevant backtrace frame (outside DevToolbar)
     *
     * @return array<int, array{file: string, line: int, function: string, class: string}>
     */
    private function getRelevantBacktrace(): array
    {
        /** @phpstan-ignore ekinoBannedCode.function (debug_backtrace required for call-location tracking) */
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($trace as $frame) {
            // Skip internal DevToolbar calls
            if (isset($frame['class']) && str_starts_with($frame['class'], 'Zappzarapp\\DevToolbar\\')) {
                continue;
            }

            // Skip PHP internal functions
            if (!isset($frame['file'])) {
                continue;
            }

            return [[
                'file'     => str_replace(getcwd() . '/', '', $frame['file']),
                'line'     => $frame['line'] ?? 0,
                'function' => $frame['function'],
                'class'    => $frame['class'] ?? '',
            ]];
        }

        return [];
    }
}
