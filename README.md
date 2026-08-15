# ⚡ zappzarapp/devtoolbar

[![Latest Version](https://img.shields.io/packagist/v/zappzarapp/devtoolbar.svg)](https://packagist.org/packages/zappzarapp/devtoolbar)
[![PHP Version](https://img.shields.io/packagist/php-v/zappzarapp/devtoolbar.svg)](https://packagist.org/packages/zappzarapp/devtoolbar)
[![License](https://img.shields.io/packagist/l/zappzarapp/devtoolbar.svg)](https://packagist.org/packages/zappzarapp/devtoolbar)
[![CI](https://github.com/marcstraube/zappzarapp-php-devtoolbar/actions/workflows/ci.yml/badge.svg)](https://github.com/marcstraube/zappzarapp-php-devtoolbar/actions/workflows/ci.yml)
[![Socket Badge](https://badge.socket.dev/composer/package/zappzarapp/devtoolbar)](https://socket.dev/composer/package/zappzarapp/devtoolbar)

In-app developer toolbar for PHP applications. Renders a mini-bar overlay with
collectors for queries, HTTP requests, cache operations, exceptions, timeline
phases, and request history, with N+1 detection and configurable performance
alerts.

The JavaScript UI ships as a pre-built asset of this PHP package (the
[symfony/web-profiler-bundle](https://github.com/symfony/web-profiler-bundle)
pattern): `composer require` is all a consumer needs — no Node toolchain, no
`npm install`.

## Requirements

- PHP **8.4+**
- `monolog/monolog` ^3.0 — `MessageCollector` extends `AbstractProcessingHandler`
- `zappzarapp/security` ^1.0 — supplies the per-request CSP nonce for the
  injected `<script>` / `<style>` tags

## Installation

```bash
composer require --dev zappzarapp/devtoolbar
```

Install it as a **dev dependency**. The toolbar is a development-time surface
and its activation guard is fail-closed (see [Activation](#activation)).

## Usage

Boot the toolbar at the top of your front controller and register an output
buffer callback that injects the toolbar HTML before `</body>`:

```php
use Zappzarapp\DevToolbar\DevToolbar;
use Zappzarapp\DevToolbar\Guard\DevToolbarGuard;

require __DIR__ . '/../vendor/autoload.php';

if (DevToolbarGuard::isEnabled()) {
    $toolbar = DevToolbar::getInstance();
    $toolbar->boot();

    // injectToolbar() runs when the buffer is flushed — a safe alternative
    // to echoing from a shutdown function.
    ob_start([$toolbar, 'injectToolbar']);
}

// ... dispatch the request and render the response as usual ...
```

`boot()` and `injectToolbar()` are both no-ops when the guard reports the
toolbar as disabled, so the two calls above are safe to leave in place; guard
them with `isEnabled()` only to avoid the output-buffer overhead in production.

### Activation

`DevToolbarGuard::isEnabled()` is **fail-closed** — the toolbar stays off
unless a development environment is proven. The decision, in order:

1. **`ENABLE_DEV_TOOLBAR`** — an explicit on/off switch. `true` (any case) or
   `1` enables; anything else, including typos, disables. When set, it decides
   **alone** — the environment variables below are not consulted. This is the
   one deliberate override of the environment gate (e.g. to enable the toolbar
   on a staging system running with production-parity environment values), so
   **pin it to `false` in production deployments**.
2. Otherwise every set one of **`APP_ENV`**, **`ENV`** and **`ZAPPZARAPP_ENV`**
   must equal one of `dev`, `development`, `local`, `test`, `testing`
   (case-insensitive), and at least one must be set. Conflicts fail closed: a
   single non-development value disables the toolbar regardless of the others.
3. There is no list of "production" values: anything not in the development
   list above — `production`, `prod`, `staging`, typos, anything unknown — as
   well as a missing environment counts as production → disabled.

The guard also disables under the CLI SAPI and for AJAX requests. Values are
read via `getenv()` with an `$_ENV` / `$_SERVER` fallback, so PHP-FPM setups
running with `clear_env=yes` do not silently fail open.

### CSP nonce

Injected inline `<script>` / `<style>` tags carry the per-request nonce from
`zappzarapp/security`'s `NonceRegistry` — there is no `unsafe-inline`
requirement. If your application generates its own nonce, hand it to the
toolbar **before** `boot()`:

```php
$toolbar->setNonce($cspNonce);
```

### Feeding collectors

`boot()` registers the default collectors and starts them. Instrument your
application by fetching a collector and recording operations as they happen:

```php
$queries = $toolbar->getCollector('queries');
$queries?->trackQuery($sql, $bindings, $durationMs);
```

Available collector names: `request`, `queries`, `http`, `cache`, `messages`,
`exceptions`, `timeline`, `history`. See the classes under
`Zappzarapp\DevToolbar\DataCollectors` for each collector's tracking API.

## Security

The toolbar exposes request internals (SQL, request/response data, exception
backtraces) to anyone who can render it. Never enable it in production, and
treat any environment reachable beyond localhost as production. See
[SECURITY.md](SECURITY.md) for the full model.

## License

MIT — see [LICENSE](LICENSE).
