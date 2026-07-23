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
 * the global environment; {@see fromGlobals} wires the production values.
 */
final readonly class GitBranchResolver
{
    private const string HEAD_REF_PREFIX = 'ref: refs/heads/';

    public function __construct(
        private string $repoRoot,
        private ?string $envOverride,
    ) {
    }

    public static function fromGlobals(): self
    {
        $env = getenv('GIT_BRANCH');

        return new self(
            repoRoot: getcwd() ?: '.',
            envOverride: ($env !== false && $env !== '') ? $env : null,
        );
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
