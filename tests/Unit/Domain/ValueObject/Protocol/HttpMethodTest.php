<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpMethod;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
    #[DataProvider('tokens')]
    public function testAnRfc9110TokenIsKeptAsWritten(string $token): void
    {
        $this->assertSame($token, (string) HttpMethod::fromString($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tokens(): iterable
    {
        yield 'a standard method' => ['POST'];
        yield 'lower case, which RFC 9110 keeps distinct' => ['patch'];
        yield 'every symbol a token admits' => ["!#$%&'*+-.^_`|~"];
    }

    #[DataProvider('notTokens')]
    public function testAnythingElseIsNoMethod(mixed $text): void
    {
        $this->assertNull(HttpMethod::tryFromString($text));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notTokens(): iterable
    {
        yield 'the empty string' => [''];
        yield 'a space' => ['GE T'];
        yield 'a surrounding space' => [' GET'];
        yield 'a trailing newline' => ["GET\n"];
        yield 'a separator' => ['GET/POST'];
        yield 'a non-ASCII letter' => ['GÉT'];
        yield 'not a string' => [null];
    }

    public function testFromStringRefusesWhatIsNoMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HttpMethod::fromString('');
    }

    public function testMethodsCompareWithTheirCase(): void
    {
        $this->assertFalse(HttpMethod::fromString('GET')->equals(HttpMethod::fromString('get')));
    }

    public function testTheSameMethodIsEqual(): void
    {
        $this->assertTrue(HttpMethod::fromString('GET')->equals(HttpMethod::fromString('GET')));
    }
}
