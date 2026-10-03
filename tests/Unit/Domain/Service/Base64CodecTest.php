<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\Base64Codec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Base64CodecTest extends TestCase
{
    #[DataProvider('canonicalEncodings')]
    public function testTryDecodeCanonicalReadsTheCanonicalEncoding(string $encoded, string $bytes): void
    {
        $this->assertSame($bytes, Base64Codec::tryDecodeCanonical($encoded));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalEncodings(): iterable
    {
        yield 'no padding needed' => ['QUJD', 'ABC'];
        yield 'one padding character' => ['QUI=', 'AB'];
        yield 'two padding characters' => ['QQ==', 'A'];
        yield 'the empty string' => ['', ''];
    }

    #[DataProvider('nonCanonicalEncodings')]
    public function testTryDecodeCanonicalRefusesANonCanonicalEncoding(string $encoded): void
    {
        $this->assertNull(Base64Codec::tryDecodeCanonical($encoded));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonCanonicalEncodings(): iterable
    {
        yield 'padding left off' => ['QUI'];
        yield 'non-zero trailing bits' => ['QUJ='];
        yield 'whitespace inside' => ['QU I='];
        yield 'a line break inside' => ["QU\nI="];
        yield 'a character outside the alphabet' => ['QU!='];
        yield 'the url-safe alphabet' => ['-_8='];
    }
}
