<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Service\Base64Codec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Base64CodecTest extends TestCase
{
    private const string BUD11_EXAMPLE_JSON_SHA256 = 'cda3d8ee478babdc79e23969f8694a5af3c564227f74938ebe7f8f0821d3865e';

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

    #[DataProvider('canonicalUnpaddedUrlEncodings')]
    public function testTryDecodeCanonicalUnpaddedUrlReadsTheCanonicalEncoding(string $encoded, string $bytes): void
    {
        $this->assertSame($bytes, Base64Codec::tryDecodeCanonicalUnpaddedUrl($encoded));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function canonicalUnpaddedUrlEncodings(): iterable
    {
        yield 'no padding needed' => ['QUJD', 'ABC'];
        yield 'one padding character left off' => ['QUI', 'AB'];
        yield 'two padding characters left off' => ['QQ', 'A'];
        yield 'the url-safe alphabet' => ['-_8', "\xfb\xff"];
        yield 'the empty string' => ['', ''];
    }

    #[DataProvider('nonCanonicalUnpaddedUrlEncodings')]
    public function testTryDecodeCanonicalUnpaddedUrlRefusesANonCanonicalEncoding(string $encoded): void
    {
        $this->assertNull(Base64Codec::tryDecodeCanonicalUnpaddedUrl($encoded));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonCanonicalUnpaddedUrlEncodings(): iterable
    {
        yield 'padding' => ['QUI='];
        yield 'non-zero trailing bits' => ['QUJ'];
        yield 'whitespace inside' => ['QU I'];
        yield 'a character outside the alphabet' => ['QU!'];
        yield 'the standard alphabet' => ['+/8'];
        yield 'one character past a whole group' => ['QUJDR'];
    }

    public function testEncodeUnpaddedUrlWritesTheUrlSafeAlphabetWithoutPadding(): void
    {
        $this->assertSame('-_8', Base64Codec::encodeUnpaddedUrl("\xfb\xff"));
    }

    public function testTryDecodeCanonicalUnpaddedUrlReadsTheBud11ExampleCredentials(): void
    {
        $header = self::bud11ExampleHeader();
        $credentials = substr($header, strlen('Nostr '));

        $this->assertSame(self::BUD11_EXAMPLE_JSON_SHA256, hash('sha256', (string) Base64Codec::tryDecodeCanonicalUnpaddedUrl($credentials)));
    }

    private static function bud11ExampleHeader(): string
    {
        $vectors = json_decode((string) file_get_contents(__DIR__.'/../../../Vectors/blossom-auth-header.json'), true, flags: JSON_THROW_ON_ERROR);

        return is_array($vectors) && is_array($vectors['vectors'] ?? null) && is_array($vectors['vectors'][0] ?? null) && is_string($vectors['vectors'][0][0] ?? null)
            ? $vectors['vectors'][0][0]
            : self::fail('blossom-auth-header.json does not lead with the BUD-11 example header');
    }
}
