<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Compliance;

use Innis\Nostr\Core\Domain\Collection\EventIdCollection;
use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\Enum\Bech32Variant;
use Innis\Nostr\Core\Domain\Service\Bech32Codec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagFilter;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WireParserRoundTripComplianceTest extends TestCase
{
    private const int ITERATIONS = 200;

    private const string BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    public function testBech32RoundTripsArbitraryPayloads(): void
    {
        foreach ([Bech32Variant::Bech32, Bech32Variant::Bech32m] as $variant) {
            for ($i = 0; $i < self::ITERATIONS; ++$i) {
                $hrp = $this->randomHrp();
                $payload = random_bytes(random_int(1, 50));

                $decoded = Bech32Codec::decode(Bech32Codec::encode($hrp, $payload, $variant), $variant);

                $this->assertNotNull($decoded);
                $this->assertSame($hrp, $decoded['hrp']);
                $this->assertSame($payload, $decoded['data']);
            }
        }
    }

    public function testBech32RejectsSingleCharacterCorruption(): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $hrp = $this->randomHrp();
            $encoded = Bech32Codec::encode($hrp, random_bytes(32));

            $position = random_int(strlen($hrp) + 1, strlen($encoded) - 1);
            $corrupted = $encoded[$position];
            do {
                $replacement = self::BECH32_CHARSET[random_int(0, 31)];
            } while ($replacement === $corrupted);
            $encoded[$position] = $replacement;

            $this->assertNull(Bech32Codec::decode($encoded));
        }
    }

    public function testNpubRoundTripsRandomPublicKeys(): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $publicKey = PublicKey::fromHex(bin2hex(random_bytes(32))) ?? throw new RuntimeException('invalid test pubkey');

            $recovered = PublicKey::fromBech32($publicKey->toBech32());

            $this->assertNotNull($recovered);
            $this->assertTrue($recovered->equals($publicKey));
        }
    }

    public function testNoteRoundTripsRandomEventIds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $eventId = EventId::fromHex(bin2hex(random_bytes(32))) ?? throw new RuntimeException('invalid test id');

            $recovered = EventId::fromBech32($eventId->toBech32());

            $this->assertNotNull($recovered);
            $this->assertTrue($recovered->equals($eventId));
        }
    }

    public function testNsecRoundTripsRandomPrivateKeys(): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $privateKey = PrivateKey::generate();

            $recovered = PrivateKey::fromBech32($privateKey->toBech32());

            $this->assertNotNull($recovered);
            $this->assertSame($privateKey->toBech32(), $recovered->toBech32());
        }
    }

    public function testEventRoundTripsThroughItsArrayForm(): void
    {
        $signer = FakeSignatureService::accepting();
        $keyPair = KeyMother::alice();

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $event = new Event(
                $keyPair->getPublicKey(),
                Timestamp::fromInt(random_int(0, 2_000_000_000)),
                EventKind::fromInt(random_int(0, 65535)),
                $this->randomTags(),
                EventContent::fromString($this->randomContent()),
            );
            $event = 1 === random_int(0, 1) ? $event->sign($keyPair, $signer) : $event;

            $array = $event->toArray();
            $recovered = Event::fromArray($array);

            $this->assertNotNull($recovered);
            $this->assertSame($array, $recovered->toArray());
        }
    }

    public function testFilterRoundTripsThroughItsArrayForm(): void
    {
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $filter = $this->randomFilter();

            $array = $filter->toArray();
            $recovered = Filter::fromArray($array);

            $this->assertNotNull($recovered);
            $this->assertEquals($array, $recovered->toArray());
        }
    }

    private function randomHrp(): string
    {
        $hrp = '';
        for ($i = 0, $length = random_int(1, 8); $i < $length; ++$i) {
            $hrp .= chr(random_int(97, 122));
        }

        return $hrp;
    }

    private function randomContent(): string
    {
        $length = random_int(0, 40);

        return 0 === $length ? '' : bin2hex(random_bytes($length));
    }

    private function randomTags(): TagCollection
    {
        $tags = [];
        for ($i = 0, $count = random_int(0, 3); $i < $count; ++$i) {
            $tags[] = Tag::fromArray(['t', bin2hex(random_bytes(4))]) ?? throw new RuntimeException('invalid test tag');
        }

        return new TagCollection($tags);
    }

    private function randomFilter(): Filter
    {
        $since = random_int(0, 1_000_000_000);

        return new Filter(
            ids: 1 === random_int(0, 1) ? EventIdCollection::fromHexValues([bin2hex(random_bytes(32))]) : null,
            authors: 1 === random_int(0, 1) ? PublicKeyCollection::fromHexValues([bin2hex(random_bytes(32))]) : null,
            kinds: 1 === random_int(0, 1) ? EventKindCollection::fromInts([random_int(0, 65535)]) : null,
            tags: 1 === random_int(0, 1) ? TagFilter::fromValues(['t' => [bin2hex(random_bytes(4))]]) : null,
            since: Timestamp::fromInt($since),
            until: Timestamp::fromInt($since + random_int(0, 1_000_000)),
            limit: random_int(1, 5000),
            search: 1 === random_int(0, 1) ? bin2hex(random_bytes(4)) : null,
        );
    }
}
