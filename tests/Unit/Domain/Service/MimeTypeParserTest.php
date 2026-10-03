<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\MimeTypeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MimeTypeParserTest extends TestCase
{
    #[DataProvider('mimeTypes')]
    public function testReadsATypeAndSubtypeOfRestrictedNames(string $value): void
    {
        $this->assertSame($value, MimeTypeParser::tryParse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mimeTypes(): iterable
    {
        yield 'image' => ['image/png'];
        yield 'structured suffix' => ['image/svg+xml'];
        yield 'vendor tree' => ['application/vnd.api+json'];
        yield 'unregistered x- subtype' => ['audio/x-wav'];
        yield 'every restricted character' => ['a0/b!#$&-^_.+z'];
        yield 'names of 127 characters' => [str_repeat('a', 127).'/'.str_repeat('b', 127)];
    }

    public function testReadsATypeWrittenInAnyCaseAsLowercase(): void
    {
        $this->assertSame('image/png', MimeTypeParser::tryParse('Image/PNG'));
    }

    #[DataProvider('notMimeTypes')]
    public function testRefusesAnythingButATypeAndSubtype(string $value): void
    {
        $this->assertNull(MimeTypeParser::tryParse($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notMimeTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'no subtype' => ['image'];
        yield 'empty subtype' => ['image/'];
        yield 'empty type' => ['/png'];
        yield 'a parameter' => ['text/plain; charset=utf-8'];
        yield 'leading whitespace' => [' image/png'];
        yield 'trailing whitespace' => ['image/png '];
        yield 'trailing newline' => ["image/png\n"];
        yield 'a wildcard' => ['image/*'];
        yield 'a space inside' => ['ima ge/png'];
        yield 'two slashes' => ['image/png/x'];
        yield 'a name starting with punctuation' => ['image/.png'];
        yield 'a name of 128 characters' => [str_repeat('a', 128).'/png'];
        yield 'non-ascii' => ['image/pñg'];
    }
}
