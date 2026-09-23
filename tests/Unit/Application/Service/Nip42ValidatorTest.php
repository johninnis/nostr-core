<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Application\Service\Nip42Validator;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip42ValidationFailure;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Nip42ValidatorTest extends TestCase
{
    private const string RELAY_URL = 'wss://relay.example.com';
    private const string CHALLENGE = 'the-challenge';
    private const int NOW = 1_700_000_000;

    public function testAConformingEventPasses(): void
    {
        $this->assertNull($this->validator()->validate($this->authEvent(), self::challenge(), self::relayUrl()));
    }

    public function testTheWrongKindIsRefusedFirst(): void
    {
        $event = $this->authEvent(kind: EventKind::TEXT_NOTE, challenge: 'wrong');

        $this->assertSame(Nip42ValidationFailure::WrongKind, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAMismatchedChallengeIsRefused(): void
    {
        $event = $this->authEvent(challenge: 'wrong');

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAMissingChallengeTagIsRefusedAsAMismatch(): void
    {
        $event = $this->authEvent(challenge: null);

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testADifferentRelayIsRefused(): void
    {
        $event = $this->authEvent(relayUrl: 'wss://other.example.com');

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testACanonicallyEqualRelayUrlIsAccepted(): void
    {
        $event = $this->authEvent(relayUrl: 'wss://Relay.Example.com:443/');

        $this->assertNull($this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAMalformedRelayTagIsRefusedAsAMismatch(): void
    {
        $event = $this->authEvent(relayUrl: 'not a url');

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testATimestampBeyondTheToleranceIsRefused(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testATimestampWithinTheToleranceIsAccepted(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 600);

        $this->assertNull($this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAFutureTimestampIsHeldToTheSameTolerance(): void
    {
        $event = $this->authEvent(createdAt: self::NOW + 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testTheToleranceIsConfigurable(): void
    {
        $event = $this->authEvent(createdAt: self::NOW - 31);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->validator(tolerance: 30)->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAnEventCarryingTwoChallengeTagsIsRefused(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
            Tag::tryFromArray([TagType::CHALLENGE, 'a-second-challenge']),
        ]);

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAnEventCarryingTwoRelayTagsIsRefused(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::RELAY, 'wss://other.example.com']),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
        ]);

        $this->assertSame(Nip42ValidationFailure::RelayMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAnEmptyChallengeTagIsRefused(): void
    {
        $event = $this->authEvent(challenge: '');

        $this->assertSame(Nip42ValidationFailure::ChallengeMismatch, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    public function testAStaleEventIsRefusedForItsTimestampBeforeItsChallengeIsRead(): void
    {
        $event = $this->authEvent(challenge: 'wrong', createdAt: self::NOW - 601);

        $this->assertSame(Nip42ValidationFailure::TimestampOutsideTolerance, $this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    // Deliberate: the signature is the event validator's business, not NIP-42's — see ADR-0066
    public function testAnEventWhoseSignatureDoesNotVerifyStillPassesNip42Validation(): void
    {
        $event = $this->authEvent();
        $rejectingVerifier = $this->createStub(SignatureServiceInterface::class);
        $rejectingVerifier->method('verify')->willReturn(false);

        $this->assertFalse($event->verify($rejectingVerifier));
        $this->assertNull($this->validator()->validate($event, self::challenge(), self::relayUrl()));
    }

    private function validator(int $tolerance = 600): Nip42Validator
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(Timestamp::fromInt(self::NOW));

        return new Nip42Validator($clock, $tolerance);
    }

    private static function challenge(): Challenge
    {
        return Challenge::fromString(self::CHALLENGE);
    }

    private static function relayUrl(): RelayUrl
    {
        return RelayUrl::tryFromString(self::RELAY_URL) ?? throw new RuntimeException('Expected a valid relay URL');
    }

    /**
     * @param list<Tag|null> $tags
     */
    private function eventWithTags(array $tags): Event
    {
        return EventMother::fromRumour(new Rumour(
            KeyMother::alicePublicKey(),
            Timestamp::fromInt(self::NOW),
            EventKind::fromInt(EventKind::CLIENT_AUTH),
            new TagCollection($tags),
            EventContent::fromString(''),
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

        return EventMother::fromRumour(new Rumour(
            KeyMother::alicePublicKey(),
            Timestamp::fromInt($createdAt),
            EventKind::fromInt($kind),
            new TagCollection($tags),
            EventContent::fromString(''),
        ));
    }

    public function testTheSameBindingTagRepeatedWithTheSameValueIsOneClaim(): void
    {
        $event = $this->eventWithTags([
            Tag::tryFromArray([TagType::RELAY, self::RELAY_URL]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
            Tag::tryFromArray([TagType::CHALLENGE, self::CHALLENGE]),
        ]);

        $this->assertNull($this->validator()->validate($event, Challenge::fromString(self::CHALLENGE), self::relayUrl()));
    }
}
