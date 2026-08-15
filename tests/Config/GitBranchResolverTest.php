<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Config;

use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Config\GitBranchResolver;

class GitBranchResolverTest extends TestCase
{
    private string $tmpRepo;

    protected function setUp(): void
    {
        $this->tmpRepo = sys_get_temp_dir() . '/devtoolbar-git-' . uniqid();
        mkdir($this->tmpRepo . '/.git', 0o755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpRepo);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function testEnvOverrideTakesPrecedenceOverHeadFile(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "ref: refs/heads/main\n");
        $resolver = new GitBranchResolver($this->tmpRepo, 'feature/from-env');

        $this->assertSame('feature/from-env', $resolver->resolve());
    }

    public function testReadsBranchFromHeadFile(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "ref: refs/heads/develop\n");
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertSame('develop', $resolver->resolve());
    }

    public function testFormatsDetachedHeadAsShortHash(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "abc123def4567890abc123def4567890abc123de\n");
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertSame('detached @ abc123d', $resolver->resolve());
    }

    public function testFormatsDetachedSha256HeadAsShortHash(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', str_repeat('ab12', 16) . "\n");
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertSame('detached @ ab12ab1', $resolver->resolve());
    }

    public function testReturnsNullForCorruptHeadContent(): void
    {
        // Neither a branch symref nor a plausible commit hash — e.g. a
        // truncated hash or a symref to a non-branch ref must not leak
        // into the label.
        foreach (["abc123def456\n", "ref: refs/tags/v1.0.0\n", "garbage content\n"] as $contents) {
            file_put_contents($this->tmpRepo . '/.git/HEAD', $contents);
            $resolver = new GitBranchResolver($this->tmpRepo, null);

            $this->assertNull($resolver->resolve(), 'HEAD content: ' . trim($contents));
        }
    }

    public function testReturnsNullWhenHeadFileMissing(): void
    {
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertNull($resolver->resolve());
    }

    public function testReturnsNullForNonGitDirectory(): void
    {
        $resolver = new GitBranchResolver('/nonexistent/path/' . uniqid(), null);

        $this->assertNull($resolver->resolve());
    }

    public function testTrimsWhitespaceAndNewlines(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "ref: refs/heads/feature/x  \r\n");
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertSame('feature/x', $resolver->resolve());
    }

    public function testEmptyEnvOverrideFallsBackToHeadFile(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "ref: refs/heads/main\n");
        // Constructor accepts only string|null, so an empty string here is treated as a deliberate value.
        // The fromGlobals() factory normalizes '' → null; this test documents the constructor contract.
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertSame('main', $resolver->resolve());
    }

    public function testDiscoverRepoRootReturnsStartDirContainingGitDirectory(): void
    {
        $this->assertSame($this->tmpRepo, GitBranchResolver::discoverRepoRoot($this->tmpRepo));
    }

    public function testDiscoverRepoRootWalksUpFromFrontControllerDirectory(): void
    {
        mkdir($this->tmpRepo . '/public');

        $this->assertSame($this->tmpRepo, GitBranchResolver::discoverRepoRoot($this->tmpRepo . '/public'));
    }

    public function testDiscoverRepoRootWalksUpMultipleLevels(): void
    {
        mkdir($this->tmpRepo . '/a/b/c', 0o755, recursive: true);

        $this->assertSame($this->tmpRepo, GitBranchResolver::discoverRepoRoot($this->tmpRepo . '/a/b/c'));
    }

    public function testDiscoverRepoRootRespectsDepthBound(): void
    {
        // Four levels above the start directory — one beyond MAX_WALK_UP_DEPTH.
        mkdir($this->tmpRepo . '/a/b/c/d', 0o755, recursive: true);

        $this->assertSame(
            $this->tmpRepo . '/a/b/c/d',
            GitBranchResolver::discoverRepoRoot($this->tmpRepo . '/a/b/c/d'),
        );
    }

    public function testDiscoverRepoRootStopsAtGitFileWithoutClimbingFurther(): void
    {
        // Worktree layout: .git is a file (gitdir: pointer) inside a directory
        // that is itself nested in a repository with a real .git directory.
        mkdir($this->tmpRepo . '/worktree/public', 0o755, recursive: true);
        file_put_contents($this->tmpRepo . '/worktree/.git', "gitdir: /somewhere/else\n");

        $this->assertSame(
            $this->tmpRepo . '/worktree',
            GitBranchResolver::discoverRepoRoot($this->tmpRepo . '/worktree/public'),
        );

        // resolve() follows the pointer, finds no HEAD there, and yields null
        // instead of showing the branch of the unrelated outer repository.
        $resolver = new GitBranchResolver($this->tmpRepo . '/worktree', null);
        $this->assertNull($resolver->resolve());
    }

    public function testFollowsAbsoluteGitdirPointerOfWorktree(): void
    {
        // Linked worktree: .git is a file whose absolute gitdir target holds
        // HEAD directly (main repo's .git/worktrees/<name>).
        mkdir($this->tmpRepo . '/.git/worktrees/wt', 0o755, recursive: true);
        file_put_contents($this->tmpRepo . '/.git/worktrees/wt/HEAD', "ref: refs/heads/feature/wt\n");
        mkdir($this->tmpRepo . '/checkout');
        file_put_contents($this->tmpRepo . '/checkout/.git', 'gitdir: ' . $this->tmpRepo . "/.git/worktrees/wt\n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/checkout', null);

        $this->assertSame('feature/wt', $resolver->resolve());
    }

    public function testFollowsRelativeGitdirPointerOfSubmodule(): void
    {
        // Submodule layout: the gitdir target is relative to the directory
        // containing the .git file.
        mkdir($this->tmpRepo . '/.git/modules/lib', 0o755, recursive: true);
        file_put_contents($this->tmpRepo . '/.git/modules/lib/HEAD', "ref: refs/heads/main\n");
        mkdir($this->tmpRepo . '/lib');
        file_put_contents($this->tmpRepo . '/lib/.git', "gitdir: ../.git/modules/lib\n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/lib', null);

        $this->assertSame('main', $resolver->resolve());
    }

    public function testFormatsDetachedHeadInWorktree(): void
    {
        mkdir($this->tmpRepo . '/.git/worktrees/wt', 0o755, recursive: true);
        file_put_contents($this->tmpRepo . '/.git/worktrees/wt/HEAD', "abc123def4567890abc123def4567890abc123de\n");
        mkdir($this->tmpRepo . '/checkout');
        file_put_contents($this->tmpRepo . '/checkout/.git', 'gitdir: ' . $this->tmpRepo . "/.git/worktrees/wt\n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/checkout', null);

        $this->assertSame('detached @ abc123d', $resolver->resolve());
    }

    public function testReturnsNullForMalformedGitFile(): void
    {
        mkdir($this->tmpRepo . '/checkout');
        file_put_contents($this->tmpRepo . '/checkout/.git', "not a pointer\n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/checkout', null);

        $this->assertNull($resolver->resolve());
    }

    public function testReturnsNullForEmptyGitdirTarget(): void
    {
        mkdir($this->tmpRepo . '/checkout');
        file_put_contents($this->tmpRepo . '/checkout/.git', "gitdir:   \n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/checkout', null);

        $this->assertNull($resolver->resolve());
    }

    public function testDoesNotFollowGitdirPointerChains(): void
    {
        // First pointer target is legitimate but contains no HEAD — instead
        // its HEAD is itself a pointer file. The resolver must read that
        // file as HEAD content (no ref: prefix → null), never follow it.
        mkdir($this->tmpRepo . '/hop', 0o755, recursive: true);
        file_put_contents($this->tmpRepo . '/hop/HEAD', 'gitdir: ' . $this->tmpRepo . "/.git\n");
        file_put_contents($this->tmpRepo . '/.git/HEAD', "ref: refs/heads/should-not-appear\n");
        mkdir($this->tmpRepo . '/checkout');
        file_put_contents($this->tmpRepo . '/checkout/.git', 'gitdir: ' . $this->tmpRepo . "/hop\n");

        $resolver = new GitBranchResolver($this->tmpRepo . '/checkout', null);

        $this->assertNull($resolver->resolve());
    }

    public function testDiscoverRepoRootFallsBackToStartDirWhenNothingFound(): void
    {
        $plain = sys_get_temp_dir() . '/devtoolbar-plain-' . uniqid();
        mkdir($plain . '/a/b', 0o755, recursive: true);

        try {
            $this->assertSame($plain . '/a/b', GitBranchResolver::discoverRepoRoot($plain . '/a/b'));
        } finally {
            rmdir($plain . '/a/b');
            rmdir($plain . '/a');
            rmdir($plain);
        }
    }
}
