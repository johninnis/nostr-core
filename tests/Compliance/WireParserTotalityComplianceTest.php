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

final class WireParserTotalityComplianceTest extends TestCase
{
    private const int ITERATIONS = 256;

    public function testEventFromJsonNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Event::tryFromJson(FuzzInputMother::hostileString());
        }
    }

    public function testEventFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Event::tryFromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testEventFromArrayRoundTripsToSerialisationWithoutThrowing(): void
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

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $event = Event::tryFromArray(FuzzInputMother::nearValidEventArray());

            if (null !== $event) {
                $this->assertJson($event->toJson());
            }
        }
    }

    public function testFilterFromArrayRoundTripsToSerialisationWithoutThrowing(): void
    {
        $baseline = Filter::tryFromArray(['kinds' => [1]]);
        $this->assertNotNull($baseline);
        $this->assertJson((string) $baseline);

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $filter = Filter::tryFromArray(FuzzInputMother::nearValidFilterArray());

            if (null !== $filter) {
                $this->assertJson((string) $filter);
            }
        }
    }

    public function testMessageDeserialiserNeverThrowsOnStructuredOrObjectInput(): void
    {
        $this->expectNotToPerformAssertions();

        $deserialiser = new JsonMessageDeserialiser();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $deserialiser->deserialiseClientMessage(FuzzInputMother::messageJson(['EVENT', 'REQ', 'CLOSE', 'AUTH', 'COUNT']));
            $deserialiser->deserialiseClientMessage(FuzzInputMother::sparseObjectJson());
            $deserialiser->deserialiseRelayMessage(FuzzInputMother::messageJson(['EVENT', 'OK', 'EOSE', 'CLOSED', 'NOTICE', 'AUTH', 'COUNT']));
            $deserialiser->deserialiseRelayMessage(FuzzInputMother::sparseObjectJson());
        }
    }

    public function testMessageFromJsonNeverThrowsOnStructuredOrObjectInput(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            ClientEventMessage::tryFromJson(FuzzInputMother::messageJson(['EVENT']));
            ClientEventMessage::tryFromJson(FuzzInputMother::sparseObjectJson());
            OkMessage::tryFromJson(FuzzInputMother::messageJson(['OK']));
            OkMessage::tryFromJson(FuzzInputMother::sparseObjectJson());
        }
    }

    public function testFilterFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Filter::tryFromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testTagCollectionFromWireNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            TagCollection::tryFromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testIdentityHexParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            PublicKey::tryFromHex(FuzzInputMother::hostileString());
            EventId::tryFromHex(FuzzInputMother::hostileString());
            Signature::tryFromHex(FuzzInputMother::hostileString());
            PrivateKey::tryFromHex(FuzzInputMother::hostileString());
        }
    }

    public function testIdentityBech32ParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            PublicKey::tryFromBech32(FuzzInputMother::hostileString());
            EventId::tryFromBech32(FuzzInputMother::hostileString());
            PrivateKey::tryFromBech32(FuzzInputMother::hostileString());
        }
    }

    public function testRelayUrlFromStringNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            RelayUrl::fromString(FuzzInputMother::hostileString());
        }
    }

    public function testNcryptsecFromStringNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Ncryptsec::tryFromString(FuzzInputMother::hostileString());
        }
    }

    public function testBech32CodecNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Bech32Codec::decode(FuzzInputMother::hostileString());
            Bech32Codec::decodeWithHrp(FuzzInputMother::hostileString(), 'npub');
        }
    }

    public function testNip19CodecNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        $codec = new Nip19Codec();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $codec->decodeComplexEntity(FuzzInputMother::hostileString());
            $codec->parseEventReference(FuzzInputMother::hostileString());
        }
    }

    public function testJsonMessageDeserialiserNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        $deserialiser = new JsonMessageDeserialiser();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $deserialiser->deserialiseClientMessage(FuzzInputMother::hostileString());
            $deserialiser->deserialiseRelayMessage(FuzzInputMother::hostileString());
        }
    }
}
