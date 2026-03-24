<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EventValidationService;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class EventValidationServiceTest extends TestCase
{
    private EventValidationService $service;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->service = new EventValidationService();
        $this->keyPair = KeyPair::generate();
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
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );
        $signedEvent = $event->sign($this->keyPair->getPrivateKey());

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
            EventKind::textNote(),
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
            EventKind::textNote(),
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
            'content' => 'Different content', // This will make signature invalid
            'sig' => $event->getSignature()?->toHex() ?? '',
        ]);

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
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );

        $this->assertFalse($this->service->isEventValid($event));
    }

    public function testUnsignedEventPassesValidationWhenNoSignaturePresent(): void
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello')
        );

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testValidationChecksContentLength(): void
    {
        $maxLengthContent = str_repeat('a', 65536); // Exactly at the limit
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString($maxLengthContent)
        );

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testValidationChecksTagCount(): void
    {
        $tags = [];
        for ($i = 0; $i < 1000; ++$i) { // Exactly at the limit
            $tags[] = Tag::hashtag("tag{$i}");
        }

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            new TagCollection($tags),
            EventContent::fromString('Hello')
        );

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    private function createValidSignedEvent(): Event
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::textNote(),
            TagCollection::empty(),
            EventContent::fromString('Hello Nostr!')
        );

        return $event->sign($this->keyPair->getPrivateKey());
    }
}
