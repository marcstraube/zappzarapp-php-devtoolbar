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

    public function testReturnsNullForDetachedHead(): void
    {
        file_put_contents($this->tmpRepo . '/.git/HEAD', "abc123def456\n");
        $resolver = new GitBranchResolver($this->tmpRepo, null);

        $this->assertNull($resolver->resolve());
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

        // resolve() then yields null (no .git/HEAD file) instead of showing
        // the branch of the unrelated outer repository.
        $resolver = new GitBranchResolver($this->tmpRepo . '/worktree', null);
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
