<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\MintKeysetCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Payment\MintKeyset;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MintKeysetCollectionTest extends TestCase
{
    private const string KEY = '02194603ffa36356f4a56b7df9371fc3192472351453ec7398b8da8117e7c3e104';

    public function testTheConstructorRejectsAnElementOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MintKeysetCollection([EventId::tryFromHex(hash('sha256', 'seed')) ?? self::fail('invalid fixture event id')]);
    }

    public function testReadsEveryKeysetOfAKeysResponseInOrder(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('a', 'sat'), self::keyset('b', 'usd')]]);

        $this->assertSame(['a', 'b'], self::idsOf($keysets));
    }

    public function testDropsAKeysetThatDoesNotRead(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('a', 'sat'), ['id' => 'x', 'unit' => 'sat'], 'k', self::keyset('b', 'usd')]]);

        $this->assertSame(['a', 'b'], self::idsOf($keysets));
    }

    public function testReadsAKeysResponseDecodedFromItsJson(): void
    {
        $response = JsonWireFormat::decode('{"keysets":[{"id":"a","unit":"sat","active":true,"keys":{"1":"k","2":"k2"}}]}');

        $this->assertSame(['a'], self::idsOf(MintKeysetCollection::fromKeysResponse($response)));
    }

    #[DataProvider('responsesWithoutKeysets')]
    public function testReadsNoKeysetsFromAResponseThatListsNone(mixed $response): void
    {
        $this->assertTrue(MintKeysetCollection::fromKeysResponse($response)->isEmpty());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function responsesWithoutKeysets(): iterable
    {
        yield 'null' => [null];
        yield 'a string' => ['{"keysets":[]}'];
        yield 'a list of keysets' => [[self::keyset('a', 'sat')]];
        yield 'an object without keysets' => [['keys' => self::keyset('a', 'sat')]];
        yield 'keysets that are one keyset' => [['keysets' => self::keyset('a', 'sat')]];
        yield 'keysets that are an object of keysets' => [JsonWireFormat::decode('{"keysets":{"0":{"id":"a","unit":"sat","keys":{"1":"k"}}}}')];
        yield 'an empty keysets list' => [['keysets' => []]];
    }

    public function testFindsTheKeysetWithAnId(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('a', 'sat'), self::keyset('b', 'usd')]]);

        $this->assertSame('usd', $keysets->findById('b')?->getUnit());
    }

    public function testFindsTheFirstKeysetWithAnIdTheResponseRepeats(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('a', 'sat'), self::keyset('a', 'usd')]]);

        $this->assertSame('sat', $keysets->findById('a')?->getUnit());
    }

    public function testFindsAKeysetOnlyByItsIdAsWritten(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('ab', 'sat')]]);

        $this->assertNull($keysets->findById('AB'));
    }

    public function testFindsNoKeysetForAnIdTheResponseDoesNotList(): void
    {
        $keysets = MintKeysetCollection::fromKeysResponse(['keysets' => [self::keyset('a', 'sat')]]);

        $this->assertNull($keysets->findById('b'));
    }

    /**
     * @return array{id: string, unit: string, keys: array<int, string>}
     */
    private static function keyset(string $id, string $unit): array
    {
        return ['id' => $id, 'unit' => $unit, 'keys' => [1 => self::KEY]];
    }

    /**
     * @return list<string>
     */
    private static function idsOf(MintKeysetCollection $keysets): array
    {
        return array_map(static fn (MintKeyset $keyset): string => $keyset->getId(), $keysets->toArray());
    }
}
