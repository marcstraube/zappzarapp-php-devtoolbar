# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in `zappzarapp/devtoolbar`, please report it
privately so it can be addressed before public disclosure.

1. Go to the
   [Security Advisories page](https://github.com/marcstraube/zappzarapp-php-devtoolbar/security/advisories/new)
2. Click **Report a vulnerability**
3. Fill in the template

Please include:

- The version affected (or commit hash if reporting against `master`)
- A reproduction (minimal code snippet or steps)
- The impact you observed and the impact you believe a real attacker could achieve

Avoid filing public GitHub issues, social-media posts, or pull requests for security
problems before they are fixed.

## Security Model

`zappzarapp/devtoolbar` is a **development-time** debugging surface. It is not designed
to ship enabled in production environments. The package bundles its own activation
guard (`Zappzarapp\DevToolbar\Guard\DevToolbarGuard`) that is **fail-closed**: it keeps
the toolbar disabled unless a development environment is proven, so a missing or
misconfigured environment is treated as production.

Activation, in order of precedence:

1. `ENABLE_DEV_TOOLBAR` — an explicit on/off switch. `true` (any case) or `1` enables;
   anything else disables.
2. Otherwise the first set of `APP_ENV` then `ENV` must equal one of `dev`,
   `development`, `local`, `test`, `testing` (case-insensitive).
3. A missing or unrecognized environment, the CLI SAPI, and AJAX requests all disable
   the toolbar.

Values are read via `getenv()` with an `$_ENV` / `$_SERVER` fallback, so a PHP-FPM
setup running with `clear_env=yes` cannot silently fail open.

### Threat surface

The toolbar reads request data, query strings, exception backtraces, and HTTP/cache
operations as the application runs. Anything visible to the toolbar is visible to
whoever can render the toolbar — by default that means a developer on their local
machine. Treat enabling the toolbar in shared environments (staging, CI) as
equivalent to opening read-only access to those request internals to everyone who
can reach those environments.

### What the package does to keep you safe

- **CSP-friendly injection** — all injected `<script>` and `<style>` tags carry the
  per-request nonce from `zappzarapp/security`'s `NonceRegistry`. There are no inline
  handlers and no `unsafe-inline` requirement.
- **HTML escaping at the boundary** — the panel renderers escape every value pulled
  from collectors before emitting HTML; user-controlled data never reaches the
  toolbar's DOM as raw markup.
- **No persistence of bodies by default** — request/response bodies are tracked in
  memory for the lifetime of the request only. Client-side history
  (`StorageManager`) stores summaries in `localStorage`; bodies are not persisted
  unless the user explicitly captures them.

### What you must do

- **Never enable the toolbar in production.** The `DevToolbarGuard` is fail-closed,
  but you control its inputs — do not set `ENABLE_DEV_TOOLBAR=true` or a development
  `APP_ENV`/`ENV` value in a production deployment.
- **Be careful with custom collectors.** Any collector you add will see the same
  request internals; sanitize before storing or rendering anything sensitive.
- **Treat staging like production** for any environment reachable beyond localhost.

## Supported Versions

Initial extraction is in progress; supported-version policy will be published with the
first tagged release.

## Security Practices

- **Strict types** enforced in all source files (`declare(strict_types=1);`)
- **Static analysis** — PHPStan (level 8) + Psalm + ekino/phpstan-banned-code
- **Dependency scanning** — Composer audit + roave/security-advisories
- **Mutation testing** — Infection (run via `composer infection`)
