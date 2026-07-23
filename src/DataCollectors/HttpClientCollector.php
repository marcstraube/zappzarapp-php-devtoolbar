<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;
use CurlHandle;

/**
 * HTTP Client Collector
 *
 * Tracks all outgoing HTTP requests (cURL, file_get_contents, etc.)
 */
class HttpClientCollector implements CollectorInterface
{
    use BacktraceTrait;

    /** @var array<int, array<string, mixed>> */
    private array $requests = [];

    /** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection PDepend crashes on property hooks */
    private bool $collecting = false;

    /**
     * Start collecting HTTP requests
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->requests   = [];
    }

    /**
     * Stop collecting HTTP requests
     */
    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'http';
    }

    public function reset(): void
    {
        $this->requests   = [];
        $this->collecting = false;
    }

    public function getBadgeCount(): ?int
    {
        $count = count($this->requests);

        return $count > 0 ? $count : null;
    }

    /**
     * Get collected HTTP request data
     */
    public function getData(): array
    {
        $totalTime = array_sum(array_column($this->requests, 'time'));

        return [
            'requests'   => $this->requests,
            'count'      => count($this->requests),
            'total_time' => round($totalTime, 2),
        ];
    }

    /**
     * Track an HTTP request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param float $time Execution time in milliseconds
     * @param int $status HTTP status code
     * @param array<string, mixed> $headers Response headers
     * @param string|false $body Response body
     * @param array<string, mixed> $requestData Request data (headers, body, etc.)
     */
    public function trackRequest(
        string $method,
        string $url,
        float $time,
        int $status,
        array $headers = [],
        string|false $body = '',
        array $requestData = []
    ): void {
        if (!$this->collecting) {
            return;
        }

        $this->requests[] = [
            'method'            => $method,
            'url'               => $url,
            'time'              => round($time, 2),
            'status'            => $status,
            'headers'           => $headers,
            'body'              => $this->filterSensitiveData($body),
            'request_data'      => $this->filterSensitiveData($requestData),
            'backtrace'         => $this->getRelevantBacktrace(),
            'performance_level' => $this->getPerformanceLevel($time),
        ];
    }

    /**
     * Wrapper for file_get_contents
     *
     * @noinspection PhpUnused Called by application code wrapping HTTP calls
     *
     * @param string $url URL to fetch
     * @param mixed ...$args Additional arguments
     * @return string|false Response content
     */
    public function wrapFileGetContents(string $url, ...$args): string|false
    {
        if (!$this->collecting) {
            return file_get_contents($url, ...$args);
        }

        $start  = hrtime(true);
        $result = file_get_contents($url, ...$args);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        // PHP only populates $http_response_header when an HTTP wrapper request
        // actually receives a response. On DNS/connect failure ($result === false)
        // or a non-HTTP stream it stays undefined, so default to an empty array
        // rather than passing null into the array-typed parsers (TypeError under
        // strict_types would turn a recoverable false into a fatal error). PHPStan
        // models the magic variable as always-defined, hence the ignore.
        $responseHeaders = $http_response_header ?? []; // @phpstan-ignore nullCoalesce.variable

        $this->trackRequest(
            'GET',
            $url,
            $time,
            $this->parseHttpStatus($responseHeaders),
            $this->parseHeaders($responseHeaders),
            $result
        );

        return $result;
    }

    /**
     * Wrapper for curl_exec
     *
     * @noinspection PhpUnused Called by application code wrapping cURL calls
     *
     * @param CurlHandle $ch cURL handle
     * @return string|bool Response content
     */
    public function wrapCurlExec(CurlHandle $ch): string|bool
    {
        if (!$this->collecting) {
            return curl_exec($ch);
        }

        $start  = hrtime(true);
        $result = curl_exec($ch);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $info = curl_getinfo($ch);

        // Note: curl_getinfo() doesn't provide HTTP method - would need to track CURLOPT_CUSTOMREQUEST
        // For now, default to 'GET' for simplicity (DevToolbar is debug-only, not production-critical)
        $method = 'GET';

        $this->trackRequest(
            $method,
            $info['url'],
            $time,
            $info['http_code'],
            [],
            is_string($result) ? $result : '',
            ['curl_info' => $info]
        );

        return $result;
    }

    /**
     * Parse HTTP status code from response headers
     *
     * @param array<int, string> $headers Response headers
     * @return int Status code
     */
    private function parseHttpStatus(array $headers): int
    {
        if ($headers === []) {
            return 0;
        }

        // First header line contains status code
        $statusLine = $headers[0] ?? '';
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Parse response headers from array to associative array
     *
     * @param array<int, string> $headers Raw response headers
     * @return array<string, mixed> Parsed headers
     */
    private function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            // Skip status line
            if (str_starts_with($header, 'HTTP/')) {
                continue;
            }

            // Parse "Name: Value" format
            if (str_contains($header, ':')) {
                [$name, $value]      = explode(':', $header, 2);
                $parsed[trim($name)] = trim($value);
            }
        }

        return $parsed;
    }


    /**
     * Filter sensitive data from request/response
     *
     * @param mixed $data Data to filter
     * @return mixed Filtered data
     */
    private function filterSensitiveData(mixed $data): mixed
    {
        if ($data === false) {
            return false;
        }

        if (is_string($data)) {
            return $this->filterSensitiveString($data);
        }

        if (is_array($data)) {
            return $this->filterSensitiveArray($data);
        }

        return $data;
    }

    /**
     * Filter sensitive patterns from a string (JSON bodies, URLs)
     */
    private function filterSensitiveString(string $data): string
    {
        if (strlen($data) > 10000) {
            return substr($data, 0, 10000) . "\n\n... (truncated, " . strlen($data) . ' bytes total)';
        }

        $patterns = [
            '/("password"\s*:\s*)"[^"]*"/',
            '/("token"\s*:\s*)"[^"]*"/',
            '/("api_key"\s*:\s*)"[^"]*"/',
            '/("secret"\s*:\s*)"[^"]*"/',
        ];

        foreach ($patterns as $pattern) {
            $result = preg_replace($pattern, '$1"[FILTERED]"', $data);
            if (is_string($result)) {
                $data = $result;
            }
        }

        return $data;
    }

    /**
     * Filter sensitive keys from an array (recursively)
     *
     * @param array<string|int, mixed> $data Array to filter
     * @return array<string|int, mixed> Filtered array
     */
    private function filterSensitiveArray(array $data): array
    {
        $sensitiveKeys = ['password', 'token', 'api_key', 'secret', 'authorization'];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = '[FILTERED]';
            } elseif (is_string($value) || is_array($value)) {
                $data[$key] = $this->filterSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Determine performance level based on execution time
     *
     * @param float $time Time in milliseconds
     * @return string Performance level: 'good', 'warning', or 'critical'
     */
    private function getPerformanceLevel(float $time): string
    {
        if ($time < 200) {
            return 'good';
        }

        if ($time < 500) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }
}
