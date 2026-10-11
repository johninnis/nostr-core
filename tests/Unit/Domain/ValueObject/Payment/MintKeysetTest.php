<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Payment\MintKeyset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MintKeysetTest extends TestCase
{
    private const string KEYSET_ID = '009a1f293253e41e';
    private const string KEY_FOR_ONE = '02194603ffa36356f4a56b7df9371fc3192472351453ec7398b8da8117e7c3e104';
    private const string KEY_FOR_TWO = '03b0f36d6d47ce14df8a7be9137712c42bcdd960b19dd02f1d4a9703b1f31d7513';

    public function testReadsTheIdAsWritten(): void
    {
        $this->assertSame(self::KEYSET_ID, self::keyset()->getId());
    }

    public function testReadsTheUnitAsWritten(): void
    {
        $this->assertSame('sat', self::keyset()->getUnit());
    }

    public function testReadsTheKeyForEachAmountItLists(): void
    {
        $this->assertSame(self::KEY_FOR_TWO, self::keyset()->getKeyFor(2));
    }

    public function testHasNoKeyForAnAmountItDoesNotList(): void
    {
        $this->assertNull(self::keyset()->getKeyFor(4));
    }

    public function testKeepsAKeyAsWrittenWhetherOrNotItIsACurvePoint(): void
    {
        $keyset = MintKeyset::tryFromArray(['id' => self::KEYSET_ID, 'unit' => 'sat', 'keys' => [1 => 'NOT A POINT']]);

        $this->assertSame('NOT A POINT', $keyset?->getKeyFor(1));
    }

    public function testReadsTheKeyForAnAmountOfZero(): void
    {
        $keyset = MintKeyset::tryFromArray(JsonWireFormat::decode('{"id":"a","unit":"sat","keys":{"0":"k"}}'));

        $this->assertSame('k', $keyset?->getKeyFor(0));
    }

    public function testReadsTheKeyForTheLargestAmountAProofCanState(): void
    {
        $keyset = MintKeyset::tryFromArray(JsonWireFormat::decode('{"id":"a","unit":"sat","keys":{"9223372036854775807":"k"}}'));

        $this->assertSame('k', $keyset?->getKeyFor(PHP_INT_MAX));
    }

    public function testKeepsAnEmptyIdAndUnitAsWritten(): void
    {
        $keyset = MintKeyset::tryFromArray(['id' => '', 'unit' => '', 'keys' => [1 => self::KEY_FOR_ONE]]);

        $this->assertSame(['', ''], [$keyset?->getId(), $keyset?->getUnit()]);
    }

    #[DataProvider('entriesThatAreNoKey')]
    public function testDropsAKeyEntryThatIsNoAmountAndKey(string $keysJson, int $amount): void
    {
        $keyset = MintKeyset::tryFromArray(JsonWireFormat::decode('{"id":"a","unit":"sat","keys":'.$keysJson.'}')) ?? self::fail('Expected a keyset');

        $this->assertNull($keyset->getKeyFor($amount));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function entriesThatAreNoKey(): iterable
    {
        yield 'an amount with a leading zero' => ['{"01":"k","2":"k2"}', 1];
        yield 'a negative amount' => ['{"-1":"k","2":"k2"}', -1];
        yield 'an amount with a plus sign' => ['{"+1":"k","2":"k2"}', 1];
        yield 'an amount with a fraction' => ['{"1.0":"k","2":"k2"}', 1];
        yield 'an amount with an exponent' => ['{"1e0":"k","2":"k2"}', 1];
        yield 'an amount with whitespace' => ['{" 1":"k","2":"k2"}', 1];
        yield 'an amount beyond the integer range' => ['{"9223372036854775808":"k","2":"k2"}', PHP_INT_MAX];
        yield 'a key that is a number' => ['{"1":5,"2":"k2"}', 1];
        yield 'a key that is null' => ['{"1":null,"2":"k2"}', 1];
        yield 'a key that is an object' => ['{"1":{"k":"k"},"2":"k2"}', 1];
    }

    public function testKeepsTheKeysBesideAnEntryItDrops(): void
    {
        $keyset = MintKeyset::tryFromArray(JsonWireFormat::decode('{"id":"a","unit":"sat","keys":{"01":"k","2":"k2"}}'));

        $this->assertSame('k2', $keyset?->getKeyFor(2));
    }

    public function testReadsAKeysetThatListsNoKeys(): void
    {
        $keyset = MintKeyset::tryFromArray(JsonWireFormat::decode('{"id":"a","unit":"sat","keys":{}}')) ?? self::fail('Expected a keyset');

        $this->assertNull($keyset->getKeyFor(1));
    }

    #[DataProvider('dataThatIsNoKeyset')]
    public function testReadsNoKeysetFromDataThatIsNotOne(mixed $data): void
    {
        $this->assertNull(MintKeyset::tryFromArray($data));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function dataThatIsNoKeyset(): iterable
    {
        yield 'null' => [null];
        yield 'a string' => [self::keysetJson()];
        yield 'a list' => [[self::KEYSET_ID, 'sat', [1 => self::KEY_FOR_ONE]]];
        yield 'no id' => [['unit' => 'sat', 'keys' => [1 => self::KEY_FOR_ONE]]];
        yield 'an id that is not a string' => [['id' => 9, 'unit' => 'sat', 'keys' => [1 => self::KEY_FOR_ONE]]];
        yield 'no unit' => [['id' => self::KEYSET_ID, 'keys' => [1 => self::KEY_FOR_ONE]]];
        yield 'a unit that is not a string' => [['id' => self::KEYSET_ID, 'unit' => null, 'keys' => [1 => self::KEY_FOR_ONE]]];
        yield 'no keys' => [['id' => self::KEYSET_ID, 'unit' => 'sat']];
        yield 'keys that are a string' => [['id' => self::KEYSET_ID, 'unit' => 'sat', 'keys' => self::KEY_FOR_ONE]];
        yield 'keys that are a list' => [JsonWireFormat::decode('{"id":"a","unit":"sat","keys":["k"]}')];
    }

    private static function keyset(): MintKeyset
    {
        return MintKeyset::tryFromArray(JsonWireFormat::decode(self::keysetJson())) ?? self::fail('Expected a keyset');
    }

    private static function keysetJson(): string
    {
        return json_encode([
            'id' => self::KEYSET_ID,
            'unit' => 'sat',
            'active' => true,
            'input_fee_ppk' => 100,
            'keys' => ['1' => self::KEY_FOR_ONE, '2' => self::KEY_FOR_TWO],
        ], JSON_THROW_ON_ERROR);
    }
}
