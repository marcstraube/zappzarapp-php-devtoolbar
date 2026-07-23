<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Guard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Zappzarapp\DevToolbar\Guard\DevToolbarGuard;
use Zappzarapp\DevToolbar\Guard\EnvironmentGuard;

/**
 * Test DevToolbarGuard security checks.
 *
 * The fail-closed environment gate is the security-critical part. It is
 * exercised through the private isDevEnvironment() via reflection, because
 * PHP_SAPI is 'cli' under PHPUnit and isEnabled() therefore always short-
 * circuits to false — testing only isEnabled() would prove nothing about
 * the environment logic (which is exactly how the previous suite passed
 * for the wrong reason).
 */
class DevToolbarGuardTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot and clear the variables the guard consults so the
        // host environment cannot leak into (or out of) these tests.
        foreach (['APP_ENV', 'ENV', 'ENABLE_DEV_TOOLBAR'] as $key) {
            $this->envBackup[$key] = getenv($key);
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }

        parent::tearDown();
    }

    private function isDevEnvironment(): bool
    {
        $guard  = new EnvironmentGuard();
        $method = new ReflectionMethod(EnvironmentGuard::class, 'isDevEnvironment');

        return (bool) $method->invoke($guard);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: bool}>
     */
    public static function environmentMatrixProvider(): array
    {
        return [
            // Fail-closed default: nothing set means production.
            'nothing set'                      => [[], false],

            // ENABLE_DEV_TOOLBAR is an explicit switch that wins outright.
            'enable=true'                      => [['ENABLE_DEV_TOOLBAR' => 'true'], true],
            'enable=TRUE (case-insensitive)'   => [['ENABLE_DEV_TOOLBAR' => 'TRUE'], true],
            'enable=1'                         => [['ENABLE_DEV_TOOLBAR' => '1'], true],
            'enable=false'                     => [['ENABLE_DEV_TOOLBAR' => 'false'], false],
            'enable=0'                         => [['ENABLE_DEV_TOOLBAR' => '0'], false],
            'enable=garbage disables'          => [['ENABLE_DEV_TOOLBAR' => 'yes'], false],
            'enable overrides prod APP_ENV'    => [['ENABLE_DEV_TOOLBAR' => 'true', 'APP_ENV' => 'production'], true],
            'disable overrides dev APP_ENV'    => [['ENABLE_DEV_TOOLBAR' => 'false', 'APP_ENV' => 'development'], false],

            // Without the switch, APP_ENV must match a known dev value.
            'app_env=development'              => [['APP_ENV' => 'development'], true],
            'app_env=dev'                      => [['APP_ENV' => 'dev'], true],
            'app_env=local'                    => [['APP_ENV' => 'local'], true],
            'app_env=Development (mixed case)' => [['APP_ENV' => 'Development'], true],
            'app_env=production'               => [['APP_ENV' => 'production'], false],
            'app_env=prod (unknown → prod)'    => [['APP_ENV' => 'prod'], false],
            'app_env=staging'                  => [['APP_ENV' => 'staging'], false],
            'app_env empty string'             => [['APP_ENV' => ''], false],

            // ENV is the generic fallback (used by this package's boilerplate).
            'env=development'                  => [['ENV' => 'development'], true],
            'env=local'                        => [['ENV' => 'local'], true],
            'env=production'                   => [['ENV' => 'production'], false],
            // APP_ENV wins over ENV when both are set.
            'app_env dev beats env prod'       => [['APP_ENV' => 'development', 'ENV' => 'production'], true],
            'app_env prod beats env dev'       => [['APP_ENV' => 'production', 'ENV' => 'development'], false],
        ];
    }

    /**
     * @param array<string, string> $env
     */
    #[DataProvider('environmentMatrixProvider')]
    public function testEnvironmentGateIsFailClosed(array $env, bool $expected): void
    {
        foreach ($env as $key => $value) {
            putenv($key . '=' . $value);
        }

        $this->assertSame($expected, $this->isDevEnvironment());
    }

    public function testEnvFallsBackToServerSuperglobalWhenGetenvIsBlind(): void
    {
        // Simulates PHP-FPM clear_env=yes: getenv() returns false but the
        // variable is present in $_SERVER. Must not silently fail open.
        $_SERVER['APP_ENV'] = 'production';
        $this->assertFalse($this->isDevEnvironment());

        $_SERVER['ENABLE_DEV_TOOLBAR'] = 'true';
        $this->assertTrue($this->isDevEnvironment());
    }

    public function testDisabledInCli(): void
    {
        // PHP_SAPI is 'cli' during PHPUnit, so isEnabled() must be false
        // even with an explicit enable flag — on both the instance guard and
        // the static facade that delegates to it.
        putenv('ENABLE_DEV_TOOLBAR=true');
        $this->assertFalse((new EnvironmentGuard())->isEnabled());
        $this->assertFalse(DevToolbarGuard::isEnabled());
    }
}
