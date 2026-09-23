<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip86Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip86ResponseTest extends TestCase
{
    public function testASuccessCarriesItsResult(): void
    {
        $response = Nip86Response::success(true);

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->getResult());
        $this->assertNull($response->getError());
    }

    public function testAFailureCarriesItsError(): void
    {
        $response = Nip86Response::failure('unknown method');

        $this->assertFalse($response->isSuccess());
        $this->assertSame('unknown method', $response->getError());
        $this->assertNull($response->getResult());
    }

    public function testAFailureWithoutAMessageIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Nip86Response::failure('');
    }

    public function testASuccessSerialisesTheResultKeyAlone(): void
    {
        $this->assertSame(['result' => ['a', 'b']], Nip86Response::success(['a', 'b'])->toArray());
    }

    public function testAFailureSerialisesTheErrorKeyAlone(): void
    {
        $this->assertSame(['error' => 'no'], Nip86Response::failure('no')->toArray());
    }

    public function testANullResultIsStillASuccess(): void
    {
        $this->assertSame('{"result":null}', Nip86Response::success(null)->toJson());
    }

    public function testTryFromArrayReadsASuccess(): void
    {
        $response = Nip86Response::tryFromArray(['result' => true]) ?? throw new RuntimeException('Expected a response');

        $this->assertTrue($response->isSuccess());
        $this->assertTrue($response->getResult());
    }

    public function testTryFromArrayReadsAFailure(): void
    {
        $response = Nip86Response::tryFromArray(['error' => 'auth-required: missing header']) ?? throw new RuntimeException('Expected a response');

        $this->assertSame('auth-required: missing header', $response->getError());
    }

    public function testTryFromArrayPrefersANonEmptyErrorOverAResult(): void
    {
        $response = Nip86Response::tryFromArray(['result' => true, 'error' => 'failed']) ?? throw new RuntimeException('Expected a response');

        $this->assertFalse($response->isSuccess());
    }

    public function testTryFromArrayTreatsANullErrorAsASuccess(): void
    {
        $response = Nip86Response::tryFromArray(['result' => 1, 'error' => null]) ?? throw new RuntimeException('Expected a response');

        $this->assertTrue($response->isSuccess());
    }

    public function testTryFromArrayRefusesAnEmptyError(): void
    {
        $this->assertNull(Nip86Response::tryFromArray(['error' => '']));
    }

    public function testTryFromArrayRefusesANonStringError(): void
    {
        $this->assertNull(Nip86Response::tryFromArray(['error' => 500]));
    }

    public function testTryFromArrayRefusesAnEnvelopeWithNeitherKey(): void
    {
        $this->assertNull(Nip86Response::tryFromArray(['status' => 'ok']));
    }

    public function testTryFromJsonRoundTrips(): void
    {
        $restored = Nip86Response::tryFromJson(Nip86Response::success(['x' => 1])->toJson()) ?? throw new RuntimeException('Expected a response');

        $this->assertSame(['x' => 1], $restored->getResult());
    }

    public function testTryFromJsonRefusesMalformedJson(): void
    {
        $this->assertNull(Nip86Response::tryFromJson('{'));
    }
}
