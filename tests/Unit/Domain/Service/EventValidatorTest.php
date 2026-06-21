<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Support\CryptoFixtures;
use PHPUnit\Framework\TestCase;

final class EventValidatorTest extends TestCase
{
    private EventValidator $service;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->service = new EventValidator(
            CryptoFixtures::signer(),
            new NipComplianceValidator(CryptoFixtures::signer()),
        );
        $this->keyPair = KeyPair::generate(CryptoFixtures::signer());
    }

    public function testValidEventPassesValidation(): void
    {
        $event = $this->createValidSignedEvent();

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testThrowsExceptionForUnreasonableTimestamp(): void
    {
        $futureTimestamp = Timestamp::fromInt(time() + 7200); // 2 hours in future
        $event = new Event(
            $this->keyPair->getPublicKey(),
            $futureTimestamp,
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );
        $signedEvent = $event->sign($this->keyPair, CryptoFixtures::signer());

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event timestamp is not reasonable');

        $this->service->validateEvent($signedEvent);
    }

    public function testThrowsExceptionForTooLongContent(): void
    {
        $longContent = str_repeat('a', 65537); // Exceeds MAX_CONTENT_LENGTH
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString($longContent)
        );

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event content exceeds maximum length');

        $this->service->validateEvent($event);
    }

    public function testThrowsExceptionForTooManyTags(): void
    {
        $tags = [];
        for ($i = 0; $i < 5001; ++$i) { // Exceeds MAX_TAGS_COUNT
            $tags[] = Tag::hashtag("tag{$i}");
        }

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection($tags),
            EventContent::fromString('Hello')
        );

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event has too many tags');

        $this->service->validateEvent($event);
    }

    public function testThrowsExceptionForInvalidSignature(): void
    {
        $event = $this->createValidSignedEvent();

        // Manually create an event with invalid signature by using wrong data
        $invalidEvent = Event::fromArray([
            'id' => $event->getId()->toHex(),
            'pubkey' => $event->getPubkey()->toHex(),
            'created_at' => $event->getCreatedAt()->toInt(),
            'kind' => $event->getKind()->toInt(),
            'tags' => $event->getTags()->toArray(),
            'content' => 'Different content',
            'sig' => $event->getSignature()?->toHex() ?? '',
        ]);

        $this->assertNotNull($invalidEvent);

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event signature is invalid');

        $this->service->validateEvent($invalidEvent);
    }

    public function testIsEventValidReturnsFalseForInvalidEvent(): void
    {
        $futureTimestamp = Timestamp::fromInt(time() + 7200);
        $event = new Event(
            $this->keyPair->getPublicKey(),
            $futureTimestamp,
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );

        $this->assertFalse($this->service->isEventValid($event));
    }

    public function testUnsignedEventIsRejected(): void
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event signature is invalid');

        $this->service->validateEvent($event);
    }

    public function testEventWithEmptySigFieldIsRejected(): void
    {
        $signed = $this->createValidSignedEvent();

        $forged = Event::fromArray([
            'id' => $signed->getId()->toHex(),
            'pubkey' => $signed->getPubkey()->toHex(),
            'created_at' => $signed->getCreatedAt()->toInt(),
            'kind' => $signed->getKind()->toInt(),
            'tags' => $signed->getTags()->toArray(),
            'content' => 'forged content claiming a known pubkey',
            'sig' => '',
        ]);

        $this->assertNotNull($forged);

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event signature is invalid');

        $this->service->validateEvent($forged);
    }

    public function testValidationChecksContentLength(): void
    {
        $maxLengthContent = str_repeat('a', 65536); // Exactly at the limit
        $event = (new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString($maxLengthContent)
        ))->sign($this->keyPair, CryptoFixtures::signer());

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testValidationChecksTagCount(): void
    {
        $tags = [];
        for ($i = 0; $i < 1000; ++$i) { // Exactly at the limit
            $tags[] = Tag::hashtag("tag{$i}");
        }

        $event = (new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection($tags),
            EventContent::fromString('Hello')
        ))->sign($this->keyPair, CryptoFixtures::signer());

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    private function createValidSignedEvent(): Event
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );

        return $event->sign($this->keyPair, CryptoFixtures::signer());
    }
}
