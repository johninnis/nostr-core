<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\JsonMessageDeserialiser;
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\EventMessage as ClientEventMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\OkMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Tests\Support\FuzzInputMother;
use PHPUnit\Framework\TestCase;
use Throwable;

final class WireParserTotalityComplianceTest extends TestCase
{
    private const int ITERATIONS = 256;

    public function testEventTryFromJsonNeverThrowsOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), Event::tryFromJson(...));
    }

    public function testEventTryFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileArray(...), Event::tryFromArray(...));
    }

    public function testEventTryFromArrayRoundTripsToSerialisationWithoutThrowing(): void
    {
        $baseline = Event::tryFromArray([
            'id' => str_repeat('a', 64),
            'pubkey' => str_repeat('a', 64),
            'created_at' => 1700000000,
            'kind' => 1,
            'tags' => [],
            'content' => 'hello',
            'sig' => str_repeat('a', 128),
        ]);
        $this->assertNotNull($baseline);
        $this->assertJson($baseline->toJson());

        $this->assertNeverThrows(FuzzInputMother::nearValidEventArray(...), static function (array $input): void {
            Event::tryFromArray($input)?->toJson();
        });
    }

    public function testFilterTryFromArrayRoundTripsToSerialisationWithoutThrowing(): void
    {
        $baseline = Filter::tryFromArray(['kinds' => [1]]);
        $this->assertNotNull($baseline);
        $this->assertJson((string) $baseline);

        $this->assertNeverThrows(FuzzInputMother::nearValidFilterArray(...), static function (array $input): void {
            $filter = Filter::tryFromArray($input);

            if (null !== $filter) {
                (string) $filter;
            }
        });
    }

    public function testMessageDeserialiserNeverThrowsOnStructuredOrObjectInput(): void
    {
        $deserialiser = new JsonMessageDeserialiser();

        $this->assertNeverThrows(
            static fn (): string => FuzzInputMother::messageJson(['EVENT', 'REQ', 'CLOSE', 'AUTH', 'COUNT']),
            $deserialiser->deserialiseClientMessage(...),
        );
        $this->assertNeverThrows(FuzzInputMother::sparseObjectJson(...), $deserialiser->deserialiseClientMessage(...));
        $this->assertNeverThrows(
            static fn (): string => FuzzInputMother::messageJson(['EVENT', 'OK', 'EOSE', 'CLOSED', 'NOTICE', 'AUTH', 'COUNT']),
            $deserialiser->deserialiseRelayMessage(...),
        );
        $this->assertNeverThrows(FuzzInputMother::sparseObjectJson(...), $deserialiser->deserialiseRelayMessage(...));
    }

    public function testMessageTryFromJsonNeverThrowsOnStructuredOrObjectInput(): void
    {
        $this->assertNeverThrows(static fn (): string => FuzzInputMother::messageJson(['EVENT']), ClientEventMessage::tryFromJson(...));
        $this->assertNeverThrows(FuzzInputMother::sparseObjectJson(...), ClientEventMessage::tryFromJson(...));
        $this->assertNeverThrows(static fn (): string => FuzzInputMother::messageJson(['OK']), OkMessage::tryFromJson(...));
        $this->assertNeverThrows(FuzzInputMother::sparseObjectJson(...), OkMessage::tryFromJson(...));
    }

    public function testFilterTryFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileArray(...), Filter::tryFromArray(...));
    }

    public function testTagCollectionTryFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileArray(...), TagCollection::tryFromArray(...));
    }

    public function testIdentityHexParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), PublicKey::tryFromHex(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), EventId::tryFromHex(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), Signature::tryFromHex(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), PrivateKey::tryFromHex(...));
    }

    public function testIdentityBech32ParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), PublicKey::tryFromBech32(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), EventId::tryFromBech32(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), PrivateKey::tryFromBech32(...));
    }

    public function testRelayUrlTryFromStringNeverThrowsOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), RelayUrl::tryFromString(...));
    }

    public function testNcryptsecTryFromStringNeverThrowsOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), Ncryptsec::tryFromString(...));
    }

    public function testBech32CodecNeverThrowsOnArbitraryStrings(): void
    {
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), Bech32Codec::decode(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), static fn (string $input): ?string => Bech32Codec::decodeWithHrp($input, 'npub'));
    }

    public function testNip19CodecNeverThrowsOnArbitraryStrings(): void
    {
        $codec = new Nip19Codec();

        $this->assertNeverThrows(FuzzInputMother::hostileString(...), $codec->decodeComplexEntity(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), $codec->parseEventReference(...));
    }

    public function testJsonMessageDeserialiserNeverThrowsOnArbitraryStrings(): void
    {
        $deserialiser = new JsonMessageDeserialiser();

        $this->assertNeverThrows(FuzzInputMother::hostileString(...), $deserialiser->deserialiseClientMessage(...));
        $this->assertNeverThrows(FuzzInputMother::hostileString(...), $deserialiser->deserialiseRelayMessage(...));
    }

    /**
     * @template TInput
     *
     * @param callable(): TInput      $makeInput
     * @param callable(TInput): mixed $parse
     */
    private function assertNeverThrows(callable $makeInput, callable $parse): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $input = $makeInput();

            try {
                $parse($input);
            } catch (Throwable $e) {
                $this->fail(sprintf(
                    'Parser threw %s on iteration %d for input %s: %s',
                    $e::class,
                    $i,
                    var_export($input, true),
                    $e->getMessage(),
                ));
            }
        }

        $this->addToAssertionCount(1);
    }
}
