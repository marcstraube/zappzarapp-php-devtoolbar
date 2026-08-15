<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

use Throwable;

/**
 * Resolves the current git branch without invoking the shell.
 *
 * Reads .git/HEAD directly (file system only, no shell_exec), following a
 * worktree/submodule gitdir: pointer file one step, and honors an optional
 * environment override useful for CI/CD. Constructor takes
 * the values explicitly so tests can drive both inputs without touching
 * the global environment; {@see fromGlobals} wires the production values
 * and discovers the repository root by walking up from getcwd() — under
 * PHP-FPM the working directory is the front controller directory
 * (typically public/), one level below the repository root.
 */
final readonly class GitBranchResolver
{
    private const string HEAD_REF_PREFIX = 'ref: refs/heads/';

    private const string GITDIR_PREFIX = 'gitdir: ';

    /**
     * How many parent directories {@see discoverRepoRoot} may climb above
     * the start directory. Bounded so a deployment without .git cannot pick
     * up an unrelated repository further up (e.g. a versioned /var/www).
     */
    private const int MAX_WALK_UP_DEPTH = 3;

    public function __construct(
        private string $repoRoot,
        private ?string $envOverride,
    ) {
    }

    public static function fromGlobals(): self
    {
        $env = getenv('GIT_BRANCH');

        return new self(
            repoRoot: self::discoverRepoRoot(getcwd() ?: '.'),
            envOverride: ($env !== false && $env !== '') ? $env : null,
        );
    }

    /**
     * Walks up from $startDir to the first directory containing a .git entry
     * and returns it; falls back to $startDir when none is found within
     * {@see MAX_WALK_UP_DEPTH} levels. The walk stops at any .git entry —
     * including a .git *file* (worktree/submodule pointer): resolve() then
     * follows the gitdir: pointer one step rather than climbing past it
     * into an unrelated parent repository.
     *
     * @internal Public only so tests can drive it with a temp directory.
     */
    public static function discoverRepoRoot(string $startDir): string
    {
        $dir = $startDir;
        for ($depth = 0; $depth <= self::MAX_WALK_UP_DEPTH; $depth++) {
            if (self::hasGitEntry($dir)) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break; // Reached the filesystem root.
            }

            $dir = $parent;
        }

        return $startDir;
    }

    private static function hasGitEntry(string $dir): bool
    {
        // Same rationale as the catch around file_get_contents() below:
        // file_exists() emits E_WARNING when the walk crosses an open_basedir
        // boundary, and consumer error handlers may convert that to an
        // ErrorException — the walk must stay defensive exactly there.
        try {
            return file_exists($dir . '/.git');
        // @phpstan-ignore catch.neverThrown (consumer error handlers may convert E_WARNING to ErrorException)
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return string|null Branch name, "detached @ <short-hash>" for a
     *                     detached HEAD, or null for a non-git directory /
     *                     unreadable or unrecognized HEAD
     */
    public function resolve(): ?string
    {
        if ($this->envOverride !== null) {
            return $this->envOverride;
        }

        $gitDir = $this->gitDir();
        if ($gitDir === null) {
            return null;
        }

        $contents = $this->readFile($gitDir . '/HEAD');
        if ($contents === null) {
            return null;
        }

        if (!str_starts_with($contents, self::HEAD_REF_PREFIX)) {
            return $this->formatDetachedHead($contents);
        }

        return trim(substr($contents, strlen(self::HEAD_REF_PREFIX)));
    }

    /**
     * Format a detached HEAD as "detached @ <short-hash>".
     *
     * A detached HEAD file holds a bare commit hash (40 hex chars, or 64
     * in a SHA-256 repository). Anything else — a symref to a non-branch
     * ref, truncated or corrupt content — yields null, so no unvalidated
     * file content ever reaches the label.
     */
    private function formatDetachedHead(string $contents): ?string
    {
        $hash = trim($contents);
        if (preg_match('/^[0-9a-f]{40}(?:[0-9a-f]{24})?$/', $hash) !== 1) {
            return null;
        }

        return 'detached @ ' . substr($hash, 0, 7);
    }

    /**
     * Locate the directory holding this checkout's HEAD file.
     *
     * Normally that is .git itself. For a worktree or submodule, .git is a
     * file whose single "gitdir: <path>" line names the real git directory
     * (worktree: <repo>/.git/worktrees/<name>; submodule: a path that may
     * be relative to the directory containing the .git file). The pointer
     * is followed exactly once — HEAD is read directly from the target, so
     * a crafted chain of pointer files cannot send the resolver on a walk.
     */
    private function gitDir(): ?string
    {
        $gitEntry = $this->repoRoot . '/.git';
        if (is_dir($gitEntry)) {
            return $gitEntry;
        }

        $contents = $this->readFile($gitEntry);
        if ($contents === null || !str_starts_with($contents, self::GITDIR_PREFIX)) {
            return null;
        }

        $line   = explode("\n", substr($contents, strlen(self::GITDIR_PREFIX)), 2)[0];
        $target = trim($line);
        if ($target === '') {
            return null;
        }

        // A relative gitdir (the submodule layout) is resolved against the
        // directory containing the .git file, per gitrepository-layout(5).
        if (!str_starts_with($target, '/')) {
            return $this->repoRoot . '/' . $target;
        }

        return $target;
    }

    /**
     * Read a file, returning null on any failure.
     *
     * The file checks and file_get_contents() emit E_WARNING on failure
     * (open_basedir boundary, permission/deletion race after the
     * is_readable() check) — a gitdir: target is especially likely to sit
     * outside open_basedir (the main repository of a worktree). The no-op
     * error handler both keeps that warning out of the page when
     * display_errors is on and sidesteps consumer error handlers that
     * would convert it to an ErrorException. The catch stays for anything
     * a handler cannot intercept (e.g. ValueError for a path containing a
     * null byte).
     */
    private function readFile(string $path): ?string
    {
        set_error_handler(static fn (): bool => true);

        try {
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }

            $contents = file_get_contents($path);
        // @phpstan-ignore catch.neverThrown (ValueError for null bytes in the path is not modeled)
        } catch (Throwable) {
            return null;
        } finally {
            restore_error_handler();
        }

        return $contents === false ? null : $contents;
    }
}
