# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1](https://github.com/marcstraube/zappzarapp-php-devtoolbar/compare/v1.1.0...v1.1.1) (2026-08-15)


### Documentation

* add project CLAUDE.md ([#36](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/36)) ([0b0ca9d](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/0b0ca9d6e6eb7f3695ff70268f937e6f7ee9d99f))

## [1.1.0](https://github.com/marcstraube/zappzarapp-php-devtoolbar/compare/v1.0.1...v1.1.0) (2026-08-15)


### Features

* **config:** follow gitdir pointer files for worktrees and submodules ([#14](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/14)) ([001b23a](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/001b23a029bff714f9216fa068252333d074ffdf)), closes [#6](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/6)
* **config:** show short commit hash for detached HEAD ([#17](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/17)) ([201b737](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/201b7377c347e70dbfabbfb0e57831f7b61c2d39)), closes [#16](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/16)
* **guard:** recognize ZAPPZARAPP_ENV and fail closed on conflicts ([#12](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/12)) ([f17d36b](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/f17d36b865d3e9de1eea2c0cd0b2db9a4d7ca15c)), closes [#11](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/11)


### Documentation

* **readme:** add package badges ([#26](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/26)) ([a35babf](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/a35babfe6530446ca33c74c82e4b063592385adb))
* **readme:** make guard precedence and fail-closed semantics explicit ([#24](https://github.com/marcstraube/zappzarapp-php-devtoolbar/issues/24)) ([6a11b2c](https://github.com/marcstraube/zappzarapp-php-devtoolbar/commit/6a11b2c824f4babb46b1db839baeebf974f10f5c))

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
