<?php

declare(strict_types=1);

namespace Zappzarapp\DevToolbar\Tests\Config\Filter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zappzarapp\DevToolbar\Config\Filter\HexColorFilter;

class HexColorFilterTest extends TestCase
{
    private HexColorFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new HexColorFilter();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeColorProvider(): array
    {
        return [
            'lowercase'        => ['#3b82f6'],
            'uppercase'        => ['#FFFFFF'],
            'mixed case'       => ['#aB12cD'],
            'all zeros'        => ['#000000'],
            'all f'            => ['#ffffff'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeColorProvider(): array
    {
        return [
            'shorthand'         => ['#fff'],
            'rgba 8-digit'      => ['#3b82f6ff'],
            'no hash'           => ['3b82f6'],
            'named color'       => ['red'],
            'rgb function'      => ['rgb(0,0,0)'],
            'css injection'     => ['#fff;background:url(x)'],
            'empty'             => [''],
            'too short'         => ['#3b82f'],
            'invalid char'      => ['#3b82gz'],
            'whitespace'        => ['  #3b82f6  '],
            'newline injection' => ["#3b82f6\nbackground:red"],
        ];
    }

    #[DataProvider('safeColorProvider')]
    public function testIsSafeAcceptsValidColors(string $color): void
    {
        $this->assertTrue($this->filter->isSafe($color));
    }

    #[DataProvider('unsafeColorProvider')]
    public function testIsSafeRejectsInvalidColors(string $color): void
    {
        $this->assertFalse($this->filter->isSafe($color));
    }

    public function testSanitizeReturnsLowercaseCanonicalForm(): void
    {
        $this->assertSame('#abcdef', $this->filter->sanitize('#ABCDEF'));
        $this->assertSame('#3b82f6', $this->filter->sanitize('#3b82f6'));
    }

    public function testSanitizeReturnsEmptyStringForUnsafeInput(): void
    {
        $this->assertSame('', $this->filter->sanitize('not a color'));
        $this->assertSame('', $this->filter->sanitize('#fff'));
    }
}
