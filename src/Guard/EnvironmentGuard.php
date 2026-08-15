<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Guard;

/**
 * Fail-closed environment guard (the default enablement policy).
 *
 * Keeps the toolbar disabled unless a development environment is proven,
 * so a missing or misconfigured environment is treated as production. Also
 * disables under the CLI SAPI and for AJAX requests.
 */
final class EnvironmentGuard implements GuardInterface
{
    /**
     * Environment values that count as a development environment.
     *
     * Only consulted when ENABLE_DEV_TOOLBAR is not set. Compared
     * case-insensitively against the ENVIRONMENT_VARS.
     */
    private const array DEV_ENVIRONMENTS = ['dev', 'development', 'local', 'test', 'testing'];

    /**
     * Environment variables that name the current environment. APP_ENV is
     * the Symfony/Laravel convention, ENV the generic fallback several
     * setups use, ZAPPZARAPP_ENV the zappzarapp ecosystem's own variable.
     * All set variables are consulted; a single non-development value
     * disables the toolbar regardless of the others.
     */
    private const array ENVIRONMENT_VARS = ['APP_ENV', 'ENV', 'ZAPPZARAPP_ENV'];

    public function isEnabled(): bool
    {
        // Layer 1: CLI detection - never load in CLI mode
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Layer 2: Fail-closed environment gate - must prove development
        if (!$this->isDevEnvironment()) {
            return false;
        }

        // Layer 3: Skip for DevToolbar internal AJAX requests
        if (isset($_GET['dev_toolbar_action'])) {
            return false;
        }

        // Layer 4: Optional - Skip for other AJAX requests
        return !$this->isAjaxRequest();
    }

    /**
     * Decide whether the current environment is a development environment.
     *
     * Fail-closed precedence:
     *   1. ENABLE_DEV_TOOLBAR, when set, is an explicit on/off switch.
     *      Only the literal 'true' (any case) or '1' enables; anything
     *      else — including typos — disables.
     *   2. Otherwise every set ENVIRONMENT_VARS value must match a known
     *      development value, and at least one must be set. Any set value
     *      that is not a development value counts as production and wins,
     *      so a leftover APP_ENV=development cannot enable the toolbar
     *      while ZAPPZARAPP_ENV=production is set. A missing or
     *      unrecognized environment is treated as production.
     */
    private function isDevEnvironment(): bool
    {
        $explicit = $this->env('ENABLE_DEV_TOOLBAR');
        if ($explicit !== null) {
            $normalized = strtolower($explicit);

            return $normalized === 'true' || $normalized === '1';
        }

        $sawDevelopment = false;
        foreach (self::ENVIRONMENT_VARS as $name) {
            $value = $this->env($name);
            if ($value === null) {
                continue;
            }

            if (!in_array(strtolower($value), self::DEV_ENVIRONMENTS, true)) {
                return false;
            }

            $sawDevelopment = true;
        }

        return $sawDevelopment;
    }

    /**
     * Read an environment variable from getenv() with $_ENV/$_SERVER
     * fallback.
     *
     * PHP-FPM commonly runs with clear_env=yes, in which case getenv()
     * returns false even though the variable is present in $_ENV/$_SERVER.
     * Consulting both prevents the guard from silently failing open.
     */
    private function env(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
