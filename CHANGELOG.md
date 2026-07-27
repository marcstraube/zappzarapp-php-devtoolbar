# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1](https://github.com/marcstraube/zappzarapp-php-devtoolbar/compare/v1.0.0...v1.0.1) (2026-07-27)


### Bug Fixes

* **config:** resolve git branch under PHP-FPM by walking up to the repo root ([#5](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/5)) ([c1b9948](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/c1b994861955cc5fb91cb9b15c2acf9fe27debc6)), closes [#4](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/4)

## [1.0.0] - 2026-07-23

Initial release, extracted from
[marcstraube/zappzarapp](https://github.com/marcstraube/zappzarapp).

### Added

#### Core

- `DevToolbar` composition root configured via a `ToolbarConfig` DTO
  (collectors, panels, guard, config source, and nonce provider — all
  defaulted), plus a `getInstance()` singleton wrapper for drop-in use
- `CollectorInterface` registry with `addCollector()`, `reset()`, and
  badge counts via `getBadgeCount()`
- HTML-only response injection before the closing `</body>` tag, with a
  Content-Type guard and CSP nonce support via
  `zappzarapp/security` (`NonceRegistry`)

#### Data collectors

- `RequestCollector` — request metadata with sensitive-key filtering
  (auth headers, passwords, tokens, credentials)
- `QueryCollector` — SQL queries with timing, backtraces, and N+1 detection
- `TimelineCollector` — request lifecycle phases with per-category
  durations, percentages, and bottleneck detection
- `CacheCollector`, `HttpClientCollector`, `ExceptionCollector`,
  `MessageCollector`, `HistoryCollector` (request history with storage)

#### Analyzers

- `PerformanceAnalyzer` — threshold-based alerts (duration, memory, query
  count/time) with warning bands
- `QueryAnalyzer` — N+1 heuristics, slow-query detection, and actionable
  suggestions

#### Guard

- Fail-closed activation: explicit `ENABLE_DEV_TOOLBAR`, else
  `APP_ENV`/`ENV` allowlist (`dev`, `development`, `local`, `test`,
  `testing`); CLI and AJAX requests always excluded
- `GuardInterface` / `EnvironmentGuard` with `DevToolbarGuard` static facade

#### UI

- Full toolbar with per-collector panels, mini bar with alert badges,
  performance trend charts, request switcher, data export, and
  configurable thresholds
- Pre-built JS/CSS bundle shipped as package assets (no consumer-side
  build step)
