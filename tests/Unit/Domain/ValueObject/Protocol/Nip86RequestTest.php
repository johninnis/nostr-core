<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip86Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip86RequestTest extends TestCase
{
    public function testARequestCarriesItsMethodAndParams(): void
    {
        $request = Nip86Request::from('banpubkey', ['abcd', 'spam']);

        $this->assertSame('banpubkey', $request->getMethod());
        $this->assertSame(['abcd', 'spam'], $request->getParams());
    }

    public function testParamsDefaultToAnEmptyList(): void
    {
        $this->assertSame([], Nip86Request::from('supportedmethods')->getParams());
    }

    public function testAnEmptyMethodIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Nip86Request::from('');
    }

    public function testToArrayIsTheWireEnvelope(): void
    {
        $this->assertSame(['method' => 'blockip', 'params' => ['10.0.0.1']], Nip86Request::from('blockip', ['10.0.0.1'])->toArray());
    }

    public function testToJsonEncodesTheEnvelope(): void
    {
        $this->assertSame('{"method":"supportedmethods","params":[]}', Nip86Request::from('supportedmethods')->toJson());
    }

    public function testTryFromArrayReadsTheEnvelope(): void
    {
        $request = Nip86Request::tryFromArray(['method' => 'banpubkey', 'params' => ['abcd']]) ?? throw new RuntimeException('Expected a request');

        $this->assertSame('banpubkey', $request->getMethod());
        $this->assertSame(['abcd'], $request->getParams());
    }

    public function testTryFromArrayAcceptsAMissingParamsList(): void
    {
        $request = Nip86Request::tryFromArray(['method' => 'supportedmethods']) ?? throw new RuntimeException('Expected a request');

        $this->assertSame([], $request->getParams());
    }

    public function testTryFromArrayRefusesAMissingMethod(): void
    {
        $this->assertNull(Nip86Request::tryFromArray(['params' => []]));
    }

    public function testTryFromArrayRefusesAnEmptyMethod(): void
    {
        $this->assertNull(Nip86Request::tryFromArray(['method' => '']));
    }

    public function testTryFromArrayRefusesANonStringMethod(): void
    {
        $this->assertNull(Nip86Request::tryFromArray(['method' => 42]));
    }

    public function testTryFromArrayRefusesNonListParams(): void
    {
        $this->assertNull(Nip86Request::tryFromArray(['method' => 'banpubkey', 'params' => ['pubkey' => 'abcd']]));
    }

    public function testTryFromJsonRoundTrips(): void
    {
        $original = Nip86Request::from('changerelayname', ['My Relay']);

        $restored = Nip86Request::tryFromJson($original->toJson()) ?? throw new RuntimeException('Expected a request');

        $this->assertSame($original->toArray(), $restored->toArray());
    }

    public function testTryFromJsonRefusesMalformedJson(): void
    {
        $this->assertNull(Nip86Request::tryFromJson('{not json'));
    }

    public function testTryFromJsonRefusesAJsonList(): void
    {
        $this->assertNull(Nip86Request::tryFromJson('["banpubkey"]'));
    }

    public function testTryFromRefusesAnEmptyMethod(): void
    {
        $this->assertNull(Nip86Request::tryFrom(''));
    }

    public function testTryFromArrayRefusesANonArray(): void
    {
        $this->assertNull(Nip86Request::tryFromArray('supportedmethods'));
    }

    public function testTryFromJsonRefusesParamsGivenAsAnEmptyObject(): void
    {
        $this->assertNull(Nip86Request::tryFromJson('{"method":"supportedmethods","params":{}}'));
    }

    public function testTryFromJsonRefusesParamsGivenAsAnObjectKeyedLikeAList(): void
    {
        $this->assertNull(Nip86Request::tryFromJson('{"method":"banpubkey","params":{"0":"a"}}'));
    }

    public function testTryFromJsonRefusesParamsGivenAsNull(): void
    {
        $this->assertNull(Nip86Request::tryFromJson('{"method":"banpubkey","params":null}'));
    }

    public function testTryFromJsonReadsAnEmptyParamsList(): void
    {
        $this->assertSame([], Nip86Request::tryFromJson('{"method":"supportedmethods","params":[]}')?->getParams());
    }
}
