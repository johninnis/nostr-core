<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip42EventChecker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class Nip42EventCheckerTest extends TestCase
{
    private const string RELAY_URL = 'wss://relay.example.com';
    private const string CHALLENGE = 'the-challenge';
    private const int NOW = 1_700_000_000;

    public function testAConformingEventPasses(): void
    {
        $this->assertNull($this->checker()->check($this->authEvent(), self::relayChallenge(), self::at()));
    }

    public function testTheWrongKindIsRefusedFirst(): void
    {
        $event = $this->authEvent(kind: EventKind::TEXT_NOTE, challenge: 'wrong');

        $this->assertSame(Nip42ValidationFailure::WrongKind, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAMismatchedChallengeIsRefused(): void
    {
        $event = $this->authEvent(challenge: 'wrong');

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAMissingChallengeTagIsRefusedAsAMismatch(): void
    {
        $event = $this->authEvent(challenge: null);

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testADifferentRelayIsRefused(): void
    {
        $event = $this->authEvent(relayUrl: 'wss://other.example.com');

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testACanonicallyEqualRelayUrlIsAccepted(): void
    {
        $event = $this->authEvent(relayUrl: 'wss://Relay.Example.com:443/');

        $this->assertNull($this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAMalformedRelayTagIsRefusedAsAMismatch(): void
    {
        $event = $this->authEvent(relayUrl: 'not a url');

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testATimestampBeyondTheToleranceIsRefused(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testATimestampWithinTheToleranceIsAccepted(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 600);

        $this->assertNull($this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAFutureTimestampIsHeldToTheSameTolerance(): void
    {
        $event = $this->authEvent(createdAt: self::NOW + 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testTheToleranceIsConfigurable(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 31);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->checker(tolerance: 30)->check($event, self::relayChallenge(), self::at()));
    }

    public function testANegativeToleranceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->checker(tolerance: -1);
    }

    public function testAZeroToleranceAcceptsAnEventCreatedNow(): void
    {
        $this->assertNull($this->checker(tolerance: 0)->check($this->authEvent(createdAt: self::NOW), self::relayChallenge(), self::at()));
    }

    public function testAnEventCarryingTwoChallengeTagsIsRefused(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
            Tag::tryFromArray([TagType::CHALLENGE, 'a-second-challenge']),
        ]);

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAnEventCarryingTwoRelayTagsIsRefused(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::RELAY, 'wss://other.example.com']),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
        ]);

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAnEmptyChallengeTagIsRefused(): void
    {
        $event = $this->authEvent(challenge: '');

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    public function testAStaleEventIsRefusedForItsTimestampBeforeItsChallengeIsRead(): void
    {
        $event = $this->authEvent(challenge: 'wrong', createdAt: self::NOW - 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    // Deliberate: the signature is the event validator's business, not NIP-42's — see ADR-0127
    public function testAnEventWhoseSignatureDoesNotVerifyStillPassesTheNip42Check(): void
    {
        $event = $this->authEvent();
        $this->assertFalse($event->verify(FakeSignatureService::rejecting()));
        $this->assertNull($this->checker()->check($event, self::relayChallenge(), self::at()));
    }

    private function checker(int $tolerance = 600): Nip42EventChecker
    {
        return new Nip42EventChecker($tolerance);
    }

    private static function relayChallenge(): RelayChallenge
    {
        return new RelayChallenge(
            RelayUrl::fromString(self::RELAY_URL),
            Challenge::fromString(self::CHALLENGE),
        );
    }

    private static function at(): Timestamp
    {
        return Timestamp::fromInt(self::NOW);
    }

    /**
     * @param list<Tag|null> $tags
     */
    private function eventWithTags(array $tags): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            EventContent::fromString(''),
            new TagCollection($tags),
            Timestamp::fromInt(self::NOW),
        ));
    }

    private function authEvent(
        int $kind = EventKind::CLIENT_AUTH,
        ?string $challenge = self::CHALLENGE,
        string $relayUrl = self::RELAY_URL,
        int $createdAt = self::NOW,
    ): Event {
        $tags = [Tag::tryFromArray([TagType::RELAY, $relayUrl])];

        if (null !== $challenge) {
            $tags[] = Tag::tryFromArray([TagType::CHALLENGE, $challenge]);
        }

        return EventMother::fromRumour(Rumour::draft(
            KeyMother::alicePublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString(''),
            new TagCollection($tags),
            Timestamp::fromInt($createdAt),
        ));
    }

    public function testTheSameBindingTagRepeatedWithTheSameValueIsOneClaim(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
        ]);

        $this->assertNull($this->checker()->check($event, self::relayChallenge(), self::at()));
    }
}
