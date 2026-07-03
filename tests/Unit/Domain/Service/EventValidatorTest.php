<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class EventValidatorTest extends TestCase
{
    private EventValidator $service;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->service = new EventValidator(
            FakeSignatureService::accepting(),
            new NipComplianceValidator(FakeSignatureService::accepting()),
        );
        $this->keyPair = KeyMother::alice();
    }

    public function testValidEventPassesValidation(): void
    {
        $event = $this->createValidSignedEvent();

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testThrowsExceptionForUnreasonableTimestamp(): void
    {
        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(time() + 7200),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('Hello')
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event timestamp is not reasonable');

        $this->service->validateEvent($event);
    }

    public function testThrowsExceptionForTooLongContent(): void
    {
        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString(str_repeat('a', 65537))
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event content exceeds maximum length');

        $this->service->validateEvent($event);
    }

    public function testThrowsExceptionForTooManyTags(): void
    {
        $tags = [];
        for ($i = 0; $i < 5001; ++$i) {
            $tags[] = Tag::hashtag("tag{$i}");
        }

        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection($tags),
            EventContent::fromString('Hello')
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event has too many tags');

        $this->service->validateEvent($event);
    }

    public function testThrowsExceptionForInvalidSignature(): void
    {
        $event = $this->createValidSignedEvent();

        $invalidEvent = Event::tryFromArray([
            'id' => $event->getId()->toHex(),
            'pubkey' => $event->getPubkey()->toHex(),
            'created_at' => $event->getCreatedAt()->toInt(),
            'kind' => $event->getKind()->toInt(),
            'tags' => $event->getTags()->toJsonArray(),
            'content' => 'Different content',
            'sig' => $event->getSignature()->toHex(),
        ]);

        $this->assertNotNull($invalidEvent);

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event signature is invalid');

        $this->service->validateEvent($invalidEvent);
    }

    public function testIsEventValidReturnsFalseForInvalidEvent(): void
    {
        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::fromInt(time() + 7200),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('Hello')
        ));

        $this->assertFalse($this->service->isEventValid($event));
    }

    public function testEmptySigFieldDoesNotParseAsAnEvent(): void
    {
        $signed = $this->createValidSignedEvent();

        $forged = Event::tryFromArray([
            'id' => $signed->getId()->toHex(),
            'pubkey' => $signed->getPubkey()->toHex(),
            'created_at' => $signed->getCreatedAt()->toInt(),
            'kind' => $signed->getKind()->toInt(),
            'tags' => $signed->getTags()->toJsonArray(),
            'content' => 'forged content claiming a known pubkey',
            'sig' => '',
        ]);

        $this->assertNull($forged);
    }

    public function testValidationChecksContentLength(): void
    {
        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString(str_repeat('a', 65536))
        ));

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    public function testValidationChecksTagCount(): void
    {
        $tags = [];
        for ($i = 0; $i < 1000; ++$i) {
            $tags[] = Tag::hashtag("tag{$i}");
        }

        $event = EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection($tags),
            EventContent::fromString('Hello')
        ));

        $this->service->validateEvent($event);
        $this->assertTrue($this->service->isEventValid($event));
    }

    private function createValidSignedEvent(): Event
    {
        return EventMother::fromRumour(new Rumour(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            new TagCollection(),
            EventContent::fromString('Hello Nostr!')
        ));
    }
}
