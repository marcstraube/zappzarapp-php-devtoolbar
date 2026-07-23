<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\DataCollectors;

/**
 * Collects HTTP request/response data
 *
 * Tracks request method, headers, body, session, cookies with
 * sensitive data filtering.
 */
class RequestCollector implements CollectorInterface
{
    private const array SENSITIVE_PATTERNS = [
        'password', 'passwd', 'pwd', 'pass', 'passphrase',
        'secret', 'token', 'api_key', 'apikey',
        'private_key', 'access_token', 'refresh_token',
        'session', 'cookie', 'authorization', 'credential', 'bearer',
        // HTTP Basic auth: $_SERVER['PHP_AUTH_PW'|'PHP_AUTH_USER'|'PHP_AUTH_DIGEST']
        // lowercases to 'php_auth_*' and matches none of the above.
        'php_auth',
        'credit_card', 'cvv', 'ssn',
    ];

    /** @var array<string, mixed> */
    private array $data = [];

    private float $startTime;

    private int $startMemory;

    public function start(): void
    {
        $this->startTime   = microtime(true);
        $this->startMemory = memory_get_usage();

        $this->data = [
            'method'   => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri'      => $_SERVER['REQUEST_URI'] ?? '/',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
            'headers'  => $this->collectHeaders(),
            'get'      => static::filterSensitiveData($_GET),
            'post'     => static::filterSensitiveData($_POST),
            'server'   => static::filterSensitiveData($_SERVER),
            'cookies'  => static::filterSensitiveData($_COOKIE),
        ];
    }

    public function stop(): void
    {
        $this->data['execution_time'] = round((microtime(true) - $this->startTime) * 1000, 2);
        $this->data['memory_peak']    = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $this->data['memory_usage']   = round((memory_get_usage() - $this->startMemory) / 1024 / 1024, 2);

        // Collect response headers if available
        if (function_exists('headers_list')) {
            $this->data['response_headers'] = $this->parseResponseHeaders(headers_list());
        }

        // Collect HTTP status code
        $this->data['status_code'] = http_response_code();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        // No badge needed - there's always exactly 1 current request
        return array_merge($this->data, [
            'count' => 0, // 0 = no badge rendered
        ]);
    }

    public function getName(): string
    {
        return 'request';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getBadgeCount(): ?int
    {
        // Always exactly one current request — no badge.
        return null;
    }

    /**
     * Collect request headers
     *
     * @return array<string, string>
     */
    private function collectHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $header           = str_replace('_', '-', substr((string) $key, 5));
                $headers[$header] = $value;
            }
        }

        return static::filterSensitiveData($headers);
    }

    /**
     * Parse response headers from headers_list()
     *
     * @param array<int, string> $headersList
     * @return array<string, string>
     */
    private function parseResponseHeaders(array $headersList): array
    {
        $headers = [];

        foreach ($headersList as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $headers[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $headers;
    }

    /**
     * Filter sensitive data from arrays
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function filterSensitiveData(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            $keyLower    = strtolower((string)$key);
            $isSensitive = array_any(self::SENSITIVE_PATTERNS, fn(string $pattern): bool => str_contains($keyLower, $pattern));

            if ($isSensitive) {
                $filtered[$key] = '[FILTERED]';
            } elseif (is_array($value)) {
                $filtered[$key] = self::filterSensitiveData($value);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}
