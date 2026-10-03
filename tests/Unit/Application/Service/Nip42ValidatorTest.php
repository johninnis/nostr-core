<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Application\Service\Nip42Validator;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip42EventChecker;
use Innis\Nostr\Core\Domain\Service\Nip42EventCheckerInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class Nip42ValidatorTest extends TestCase
{
    private const string RELAY_URL = 'wss://relay.example.com';
    private const string CHALLENGE = 'the-challenge';
    private const int NOW = 1_700_000_000;

    public function testChecksTheEventAtTheClocksInstant(): void
    {
        $event = $this->authEvent(self::NOW);
        $relayChallenge = self::relayChallenge();
        $checker = $this->createMock(Nip42EventCheckerInterface::class);
        $checker->expects($this->once())
            ->method('check')
            ->with($event, $relayChallenge, Timestamp::fromInt(self::NOW))
            ->willReturn(null);

        new Nip42Validator($checker, $this->clockAt(self::NOW))->validate($event, $relayChallenge);
    }

    public function testReturnsTheFailureItsCheckerReports(): void
    {
        $checker = $this->createStub(Nip42EventCheckerInterface::class);
        $checker->method('check')->willReturn(Nip42ValidationFailure::RelayMismatch);

        $this->assertSame(
            Nip42ValidationFailure::RelayMismatch,
            new Nip42Validator($checker, $this->clockAt(self::NOW))->validate($this->authEvent(self::NOW), self::relayChallenge()),
        );
    }

    public function testAnEventFromBeyondTheToleranceOfTheClockIsRefused(): void
    {
        $validator = new Nip42Validator(new Nip42EventChecker(), $this->clockAt(self::NOW + 601));

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $validator->validate($this->authEvent(self::NOW), self::relayChallenge()));
    }

    public function testAConformingEventPasses(): void
    {
        $validator = new Nip42Validator(new Nip42EventChecker(), $this->clockAt(self::NOW));

        $this->assertNull($validator->validate($this->authEvent(self::NOW), self::relayChallenge()));
    }

    private function clockAt(int $now): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(Timestamp::fromInt($now));

        return $clock;
    }

    private static function relayChallenge(): RelayChallenge
    {
        return new RelayChallenge(
            RelayUrl::fromString(self::RELAY_URL),
            Challenge::fromString(self::CHALLENGE),
        );
    }

    private function authEvent(int $createdAt): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            EventContent::fromString(''),
            new TagCollection([
                Tag::fromArray([TagType::RELAY, self::RELAY_URL]),
                Tag::fromArray([TagType::CHALLENGE, self::CHALLENGE]),
            ]),
            Timestamp::fromInt($createdAt),
        ));
    }
}
