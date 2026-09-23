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
        $request = new Nip86Request('banpubkey', ['abcd', 'spam']);

        $this->assertSame('banpubkey', $request->getMethod());
        $this->assertSame(['abcd', 'spam'], $request->getParams());
    }

    public function testParamsDefaultToAnEmptyList(): void
    {
        $this->assertSame([], new Nip86Request('supportedmethods')->getParams());
    }

    public function testAnEmptyMethodIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip86Request('');
    }

    public function testToArrayIsTheWireEnvelope(): void
    {
        $this->assertSame(['method' => 'blockip', 'params' => ['10.0.0.1']], new Nip86Request('blockip', ['10.0.0.1'])->toArray());
    }

    public function testToJsonEncodesTheEnvelope(): void
    {
        $this->assertSame('{"method":"supportedmethods","params":[]}', new Nip86Request('supportedmethods')->toJson());
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
        $original = new Nip86Request('changerelayname', ['My Relay']);

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
}
