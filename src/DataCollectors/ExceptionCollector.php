<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

use Throwable;

/**
 * Collects exceptions thrown during request
 *
 * Tracks both handled and unhandled exceptions with stack traces.
 */
class ExceptionCollector implements CollectorInterface
{
    private static ?self $instance = null;

    /** @var array<int, array<string, mixed>> */
    private array $exceptions = [];

    private bool $collecting  = false;

    public static function getInstance(): self
    {
        if (!self::$instance instanceof ExceptionCollector) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function start(): void
    {
        $this->collecting = true;
    }

    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Track an exception that was caught and handled.
     */
    public function trackHandled(Throwable $exception): void
    {
        $this->record($exception, handled: true);
    }

    /**
     * Track an unhandled exception (one that propagated to the error boundary).
     */
    public function trackUnhandled(Throwable $exception): void
    {
        $this->record($exception, handled: false);
    }

    private function record(Throwable $exception, bool $handled): void
    {
        if (!$this->collecting) {
            return;
        }

        $this->exceptions[] = [
            'class'   => $exception::class,
            'message' => $exception->getMessage(),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => $this->formatTrace($exception->getTrace()),
            'handled' => $handled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return [
            'exceptions'      => $this->exceptions,
            'count'           => count($this->exceptions),
            'handled_count'   => count(array_filter($this->exceptions, fn(array $e) => $e['handled'])),
            'unhandled_count' => count(array_filter($this->exceptions, fn(array $e): bool => !$e['handled'])),
        ];
    }

    public function getName(): string
    {
        return 'exceptions';
    }

    public function getBadgeCount(): ?int
    {
        $count = count($this->exceptions);

        return $count > 0 ? $count : null;
    }

    /**
     * Format exception trace for display
     *
     * @param array<int, array<string, mixed>> $trace
     * @return array<int, array<string, mixed>>
     */
    private function formatTrace(array $trace): array
    {
        $formatted = [];

        foreach ($trace as $index => $frame) {
            $formatted[] = [
                'file'     => $frame['file'] ?? '[internal function]',
                'line'     => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];

            // Limit to 20 frames
            if ($index >= 19) {
                break;
            }
        }

        return $formatted;
    }

    /**
     * Reset exceptions (for testing)
     */
    public function reset(): void
    {
        $this->exceptions = [];
        $this->collecting = false;
    }
}
