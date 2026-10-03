<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\CreatedAtWindow;
use Innis\Nostr\Core\Domain\ValueObject\EventLimits;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\EventMother;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class EventValidatorTest extends TestCase
{
    private const string TARGET_EVENT_ID = 'b1b2b3b4b5b6b7b8b9b0c1c2c3c4c5c6c7c8c9c0d1d2d3d4d5d6d7d8d9d0e1e2';

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

        $this->service->validateEvent($event, Timestamp::now());
        $this->assertTrue($this->service->isEventValid($event, Timestamp::now()));
    }

    public function testThrowsExceptionForACreatedAtOutsideTheWindow(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello'),
            new TagCollection(),
            Timestamp::fromInt(1_700_007_200),
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event created_at is outside the accepted window');

        $this->service->validateEvent($event, Timestamp::fromInt(1_700_000_000));
    }

    public function testTheTimestampIsJudgedAgainstTheReferenceInstantGiven(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello'),
            new TagCollection(),
            Timestamp::fromInt(1_000_000_000),
        ));

        $this->assertSame(
            [true, false],
            [
                $this->service->isEventValid($event, Timestamp::fromInt(1_000_000_060)),
                $this->service->isEventValid($event, Timestamp::fromInt(1_700_000_000)),
            ],
        );
    }

    public function testThrowsExceptionForTooLongContent(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString(str_repeat('a', 65537)),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event content exceeds maximum length');

        $this->service->validateEvent($event, Timestamp::now());
    }

    public function testThrowsExceptionForTooManyTags(): void
    {
        $tags = [];
        for ($i = 0; $i < 5001; ++$i) {
            $tags[] = Tag::hashtag(Hashtag::fromString("tag{$i}"));
        }

        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello'),
            new TagCollection($tags),
            Timestamp::now(),
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event has too many tags');

        $this->service->validateEvent($event, Timestamp::now());
    }

    public function testAppliesTheContentLengthItIsConfiguredWith(): void
    {
        $event = $this->textNoteWithContent(str_repeat('a', 100_000));

        $this->assertSame(
            [false, true],
            [
                $this->service->isEventValid($event, Timestamp::now()),
                $this->validatorWith(new EventLimits(maxContentLength: 100_000))->isEventValid($event, Timestamp::now()),
            ],
        );
    }

    public function testAppliesTheTagCountItIsConfiguredWith(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            tags: new TagCollection([Tag::hashtag(Hashtag::fromString('one')), Tag::hashtag(Hashtag::fromString('two'))]),
        ));

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Event has too many tags');

        $this->validatorWith(new EventLimits(maxTagCount: 1))->validateEvent($event, Timestamp::now());
    }

    public function testAppliesTheCreatedAtWindowItIsConfiguredWith(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            createdAt: Timestamp::fromInt(1_700_000_120),
        ));
        $reference = Timestamp::fromInt(1_700_000_000);

        $this->assertSame(
            [true, false],
            [
                $this->service->isEventValid($event, $reference),
                $this->validatorWith(new EventLimits(createdAtWindow: new CreatedAtWindow(secondsAhead: 60)))->isEventValid($event, $reference),
            ],
        );
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

        $this->service->validateEvent($invalidEvent, Timestamp::now());
    }

    public function testIsEventValidReturnsFalseForInvalidEvent(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello'),
            new TagCollection(),
            Timestamp::fromInt(1_700_007_200),
        ));

        $this->assertFalse($this->service->isEventValid($event, Timestamp::fromInt(1_700_000_000)));
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
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString(str_repeat('a', 65536)),
            new TagCollection(),
            Timestamp::now(),
        ));

        $this->service->validateEvent($event, Timestamp::now());
        $this->assertTrue($this->service->isEventValid($event, Timestamp::now()));
    }

    public function testValidationChecksTagCount(): void
    {
        $tags = [];
        for ($i = 0; $i < 1000; ++$i) {
            $tags[] = Tag::hashtag(Hashtag::fromString("tag{$i}"));
        }

        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello'),
            new TagCollection($tags),
            Timestamp::now(),
        ));

        $this->service->validateEvent($event, Timestamp::now());
        $this->assertTrue($this->service->isEventValid($event, Timestamp::now()));
    }

    public function testThrowsForAnAddressableEventWhoseDTagsDisagree(): void
    {
        $event = $this->addressableEventWithDTags('one', 'two');

        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('Addressable event d tags disagree');

        $this->service->validateEvent($event, Timestamp::now());
    }

    public function testAcceptsAnAddressableEventThatRepeatsOneDTag(): void
    {
        $this->assertTrue($this->service->isEventValid($this->addressableEventWithDTags('one', 'one'), Timestamp::now()));
    }

    public function testAcceptsARegularEventWhoseDTagsDisagree(): void
    {
        $event = EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            tags: new TagCollection([Tag::identifier('one'), Tag::identifier('two')]),
        ));

        $this->assertTrue($this->service->isEventValid($event, Timestamp::now()));
    }

    public function testVerifiesADeletionsSignatureOnce(): void
    {
        $signer = $this->createMock(SignatureServiceInterface::class);
        $signer->expects($this->once())->method('verify')->willReturn(true);
        $validator = new EventValidator($signer, new NipComplianceValidator($signer));

        $validator->validateEvent($this->deletionWithTags([Tag::fromArray(['e', self::TARGET_EVENT_ID])]), Timestamp::now());
    }

    public function testThrowsForADeletionThatNamesNoTarget(): void
    {
        $this->expectException(InvalidEventException::class);
        $this->expectExceptionMessage('NIP-09 events must have at least one e or a tag');

        $this->service->validateEvent($this->deletionWithTags([]), Timestamp::now());
    }

    /**
     * @param list<Tag> $tags
     */
    private function deletionWithTags(array $tags): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::EVENT_DELETION),
            tags: new TagCollection($tags),
        ));
    }

    private function addressableEventWithDTags(string ...$identifiers): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::LONGFORM_CONTENT),
            tags: new TagCollection(array_map(Tag::identifier(...), array_values($identifiers))),
        ));
    }

    private function validatorWith(EventLimits $limits): EventValidator
    {
        return new EventValidator(
            FakeSignatureService::accepting(),
            new NipComplianceValidator(FakeSignatureService::accepting()),
            $limits,
        );
    }

    private function textNoteWithContent(string $content): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString($content),
        ));
    }

    private function createValidSignedEvent(): Event
    {
        return EventMother::fromRumour(Rumour::draft(
            $this->keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::TEXT_NOTE),
            EventContent::fromString('Hello Nostr!'),
            new TagCollection(),
            Timestamp::now(),
        ));
    }
}
