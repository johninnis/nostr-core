<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Exception\Nip98ValidationException;
use Innis\Nostr\Core\Domain\Service\Nip98ValidationService;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use PHPUnit\Framework\TestCase;

final class Nip98ValidationServiceTest extends TestCase
{
    private Nip98ValidationService $service;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->service = new Nip98ValidationService();
        $this->keyPair = KeyPair::generate();
    }

    public function testValidEventReturnsPublicKey(): void
    {
        $event = $this->createValidSignedEvent();

        $pubkey = $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST',
            hash('sha256', '{"method":"test"}')
        );

        $this->assertTrue($pubkey->equals($this->keyPair->getPublicKey()));
    }

    public function testRejectsWrongKind(): void
    {
        $event = $this->createSignedEvent(EventKind::textNote());

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event must be kind 27235');

        $this->service->validate($event, 'https://relay.example.com/', 'POST');
    }

    public function testRejectsUnsignedEvent(): void
    {
        $event = $this->createUnsignedEvent();

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event must be signed');

        $this->service->validate($event, 'https://relay.example.com/', 'POST');
    }

    public function testRejectsExpiredTimestamp(): void
    {
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 120));

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event timestamp is outside tolerance');

        $this->service->validate($event, 'https://relay.example.com/', 'POST');
    }

    public function testAcceptsTimestampWithinTolerance(): void
    {
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 30));

        $pubkey = $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST',
            hash('sha256', '{"method":"test"}')
        );

        $this->assertTrue($pubkey->equals($this->keyPair->getPublicKey()));
    }

    public function testRejectsMissingUrlTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event missing u tag');

        $this->service->validate($event, 'https://relay.example.com/', 'POST');
    }

    public function testRejectsWrongUrl(): void
    {
        $event = $this->createValidSignedEvent();

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('URL in u tag does not match request URL');

        $this->service->validate($event, 'https://other-relay.example.com/', 'POST');
    }

    public function testRejectsMissingMethodTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event missing method tag');

        $this->service->validate($event, 'https://relay.example.com/', 'POST');
    }

    public function testRejectsWrongMethod(): void
    {
        $event = $this->createValidSignedEvent();

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Method in method tag does not match request method');

        $this->service->validate($event, 'https://relay.example.com/', 'GET');
    }

    public function testRejectsMissingPayloadTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event missing payload tag');

        $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST',
            hash('sha256', 'body')
        );
    }

    public function testRejectsWrongPayloadHash(): void
    {
        $event = $this->createValidSignedEvent();

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Payload hash does not match request body');

        $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST',
            hash('sha256', 'different body')
        );
    }

    public function testSkipsPayloadValidationWhenHashNotProvided(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $pubkey = $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST'
        );

        $this->assertTrue($pubkey->equals($this->keyPair->getPublicKey()));
    }

    public function testUrlNormalisationMatchesWithTrailingSlash(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $pubkey = $this->service->validate(
            $event,
            'https://relay.example.com',
            'GET'
        );

        $this->assertTrue($pubkey->equals($this->keyPair->getPublicKey()));
    }

    public function testMethodComparisonIsCaseInsensitive(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'post']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $pubkey = $this->service->validate(
            $event,
            'https://relay.example.com/',
            'POST'
        );

        $this->assertTrue($pubkey->equals($this->keyPair->getPublicKey()));
    }

    public function testCustomTimestampTolerance(): void
    {
        $service = new Nip98ValidationService(timestampTolerance: 10);
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 30));

        $this->expectException(Nip98ValidationException::class);
        $this->expectExceptionMessage('Event timestamp is outside tolerance');

        $service->validate($event, 'https://relay.example.com/', 'POST');
    }

    private function createValidSignedEvent(): Event
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
            Tag::fromArray(['payload', hash('sha256', '{"method":"test"}')]),
        ]);

        return $this->createSignedEventWithTags($tags);
    }

    private function createSignedEvent(EventKind $kind): Event
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            $kind,
            $tags,
            EventContent::empty()
        );

        return $event->sign($this->keyPair->getPrivateKey());
    }

    private function createUnsignedEvent(): Event
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);

        return new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::httpAuth(),
            $tags,
            EventContent::empty()
        );
    }

    private function createSignedEventWithTimestamp(Timestamp $timestamp): Event
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
            Tag::fromArray(['payload', hash('sha256', '{"method":"test"}')]),
        ]);

        $event = new Event(
            $this->keyPair->getPublicKey(),
            $timestamp,
            EventKind::httpAuth(),
            $tags,
            EventContent::empty()
        );

        return $event->sign($this->keyPair->getPrivateKey());
    }

    private function createSignedEventWithTags(TagCollection $tags): Event
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::httpAuth(),
            $tags,
            EventContent::empty()
        );

        return $event->sign($this->keyPair->getPrivateKey());
    }
}
