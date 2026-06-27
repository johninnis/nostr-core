<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\Service\Nip19Codec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Infrastructure\Encoding\JsonMessageDeserialiser;
use Innis\Nostr\Core\Tests\Support\FuzzInputMother;
use PHPUnit\Framework\TestCase;

final class WireParserTotalityComplianceTest extends TestCase
{
    private const int ITERATIONS = 256;

    public function testEventFromJsonNeverThrowsOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Event::fromJson(FuzzInputMother::hostileString());
        }
    }

    public function testEventFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Event::fromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testFilterFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            Filter::fromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testTagCollectionFromArrayNeverThrowsOnArbitraryArrays(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            TagCollection::fromArray(FuzzInputMother::hostileArray());
        }
    }

    public function testIdentityHexParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            PublicKey::fromHex(FuzzInputMother::hostileString());
            EventId::fromHex(FuzzInputMother::hostileString());
            Signature::fromHex(FuzzInputMother::hostileString());
            PrivateKey::fromHex(FuzzInputMother::hostileString());
        }
    }

    public function testIdentityBech32ParsersNeverThrowOnArbitraryStrings(): void
    {
        $this->expectNotToPerformAssertions();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            PublicKey::fromBech32(FuzzInputMother::hostileString());
            EventId::fromBech32(FuzzInputMother::hostileString());
            PrivateKey::fromBech32(FuzzInputMother::hostileString());
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
            Ncryptsec::fromString(FuzzInputMother::hostileString());
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
