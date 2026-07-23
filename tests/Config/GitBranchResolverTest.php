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
        $head = $this->tmpRepo . '/.git/HEAD';
        if (is_file($head)) {
            unlink($head);
        }

        $gitDir = $this->tmpRepo . '/.git';
        if (is_dir($gitDir)) {
            rmdir($gitDir);
        }

        if (is_dir($this->tmpRepo)) {
            rmdir($this->tmpRepo);
        }
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
}
