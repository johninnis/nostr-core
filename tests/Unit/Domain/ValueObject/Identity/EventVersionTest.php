<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventVersion;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventVersionTest extends TestCase
{
    private const string LOWER_ID = '0000000000000000000000000000000000000000000000000000000000000001';
    private const string HIGHER_ID = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

    public function testALaterTimestampSupersedesAnEarlierOne(): void
    {
        $this->assertTrue(self::version(200, self::HIGHER_ID)->supersedes(self::version(100, self::LOWER_ID)));
    }

    public function testAnEarlierTimestampDoesNotSupersedeALaterOne(): void
    {
        $this->assertFalse(self::version(100, self::LOWER_ID)->supersedes(self::version(200, self::HIGHER_ID)));
    }

    public function testOnEqualTimestampsTheLowerIdSupersedes(): void
    {
        $this->assertTrue(self::version(100, self::LOWER_ID)->supersedes(self::version(100, self::HIGHER_ID)));
    }

    public function testOnEqualTimestampsTheHigherIdDoesNotSupersede(): void
    {
        $this->assertFalse(self::version(100, self::HIGHER_ID)->supersedes(self::version(100, self::LOWER_ID)));
    }

    public function testAVersionCarriesTheIdItWasBuiltFrom(): void
    {
        $this->assertSame(self::LOWER_ID, self::version(100, self::LOWER_ID)->getId()->toHex());
    }

    public function testAVersionNeverSupersedesItself(): void
    {
        $this->assertFalse(self::version(100, self::LOWER_ID)->supersedes(self::version(100, self::LOWER_ID)));
    }

    public function testTheIdTieBreakComparesTheWholeIdNotJustItsFirstByte(): void
    {
        $sharedPrefix = str_repeat('ab', 20);

        $this->assertTrue(
            self::version(100, $sharedPrefix.str_repeat('0', 24))
                ->supersedes(self::version(100, $sharedPrefix.str_repeat('f', 24))),
        );
    }

    public function testAVersionIsReadOffAnEvent(): void
    {
        $event = EventMother::fromRumour(new Rumour(
            KeyMother::alicePublicKey(),
            Timestamp::fromInt(100),
            EventKind::fromInt(EventKind::METADATA),
            new TagCollection([]),
            EventContent::fromString(''),
        ));

        $this->assertTrue(EventVersion::of($event)->supersedes(self::version(99, self::LOWER_ID)));
    }

    private static function version(int $createdAt, string $idHex): EventVersion
    {
        return new EventVersion(
            Timestamp::fromInt($createdAt),
            EventId::tryFromHex($idHex) ?? throw new RuntimeException('Expected a valid event id'),
        );
    }
}
