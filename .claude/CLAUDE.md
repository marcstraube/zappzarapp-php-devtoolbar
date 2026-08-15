# Claude Instructions

In-app developer toolbar for PHP (library, `zappzarapp/devtoolbar`). The
JavaScript UI ships as a pre-built asset of this package — consumers only need
`composer require`, no Node toolchain.

## PHP toolchain

Run all PHP tasks through the `make` targets — the Makefile auto-detects the
PHP interpreter (`php84` → `php8.4` → `php`, overridable via
`make PHP=... <target>`), so composer, PHPUnit, and the analysis tools all
run on the same binary:

```
make install   # composer install via $(PHP)
make test      # PHPUnit
make check     # security, cs, PHPStan, Psalm, PHPMD, Rector, Deptrac, tests
```

Don't invoke `composer`/`vendor/bin/*` with a bare `php` — use the pinned
interpreter (`php84 $(command -v composer) ...`) so extension availability
matches the Makefile toolchain.

## Frontend

- Sources live in `frontend/` (pnpm, Node ≥ 22, `.nvmrc`); tests run on
  vitest + happy-dom: `pnpm run type-check && pnpm run lint && pnpm test`.
- The bundle is **committed to `assets/devtoolbar.js`**. After any change to
  frontend code or dependencies, rebuild (`pnpm run build` /
  `make frontend-build`) and commit the bundle alongside.
- The CI job "Frontend Bundle Build" also runs type-check, lint, and the
  vitest suite despite its name — frontend tests are CI-covered.
- `@zappzarapp/browser-utils` is the first-party utility library: before
  hand-rolling cookie/storage/event/focus/color logic, check whether a module
  exists. Trap: `CookieManager` defaults to `secure: true, sameSite:
  'Strict'` — such cookies are silently dropped on HTTP dev hosts; pass
  `{ secure: false, sameSite: 'Lax' }` explicitly.

## Security model (the guard is security-critical)

The toolbar exposes SQL, request data, and backtraces. `EnvironmentGuard` is
**fail-closed**:

- There is only a development **allowlist** (`dev`, `development`, `local`,
  `test`, `testing`) — no list of production values. Any set value outside
  the allowlist (including `prod`, `staging`, typos) disables the toolbar.
  Conflicts between `APP_ENV`/`ENV`/`ZAPPZARAPP_ENV` disable it as well.
- `ENABLE_DEV_TOOLBAR`, when set, decides **alone** (documented operator
  override, e.g. for staging with production-parity environment values).
- Develop any change to this logic against the matrix in
  `tests/Guard/DevToolbarGuardTest.php` and justify it fail-closed.

## Conventions & workflow

- Conventional Commits (`type(scope): message`); releases via release-please
  (`feat`/`fix` appear in the changelog, `chore` does not — pick the type
  deliberately).
- Commits must be **GPG-signed** (the "Verify GPG Signatures" CI job checks
  every PR commit).
- PR workflow: branch → PR → CI → squash merge. The repo requires
  **up-to-date branches**: rebase onto current `master` before every merge;
  with several open PRs, plan the merge order accordingly.
- **Never update dependabot branches via API/`gh pr update-branch --rebase`**
  — that rewrites the commits unsigned and turns the signature check red.
  Comment `@dependabot rebase` instead (`recreate` after foreign changes to
  its branch).
- Dependabot covers composer, npm (`/frontend`), and GitHub Actions;
  grouped minor/patch updates auto-merge after green CI, majors stay open
  for manual review.
