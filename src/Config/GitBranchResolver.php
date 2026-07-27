<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Config;

use Throwable;

/**
 * Resolves the current git branch without invoking the shell.
 *
 * Reads .git/HEAD directly (file system only, no shell_exec) and honors
 * an optional environment override useful for CI/CD. Constructor takes
 * the values explicitly so tests can drive both inputs without touching
 * the global environment; {@see fromGlobals} wires the production values
 * and discovers the repository root by walking up from getcwd() — under
 * PHP-FPM the working directory is the front controller directory
 * (typically public/), one level below the repository root.
 */
final readonly class GitBranchResolver
{
    private const string HEAD_REF_PREFIX = 'ref: refs/heads/';

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
     * yields null rather than climbing past it into an unrelated parent
     * repository.
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
     * @return string|null Branch name, or null for detached HEAD / non-git directory
     */
    public function resolve(): ?string
    {
        if ($this->envOverride !== null) {
            return $this->envOverride;
        }

        $headPath = $this->repoRoot . '/.git/HEAD';
        if (!is_file($headPath) || !is_readable($headPath)) {
            return null;
        }

        // Not dead code: file_get_contents() emits E_WARNING on a read failure
        // (e.g. a permission/deletion race after the is_readable() check), and
        // consumers may install error handlers that turn warnings into
        // ErrorException — without the catch that would break the page render.
        try {
            $contents = file_get_contents($headPath);
        // @phpstan-ignore catch.neverThrown (consumer error handlers may convert E_WARNING to ErrorException)
        } catch (Throwable) {
            return null;
        }

        if ($contents === false) {
            return null;
        }

        if (!str_starts_with($contents, self::HEAD_REF_PREFIX)) {
            // Detached HEAD: contents is a commit hash, not a branch reference.
            return null;
        }

        return trim(substr($contents, strlen(self::HEAD_REF_PREFIX)));
    }
}
