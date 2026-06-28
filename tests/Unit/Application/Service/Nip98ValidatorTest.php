<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\DTO\Nip98Request;
use Innis\Nostr\Core\Application\Port\Nip98ReplayGuardInterface;
use Innis\Nostr\Core\Application\Service\Nip98Validator;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Time\SystemClock;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class Nip98ValidatorTest extends TestCase
{
    private Nip98Validator $service;
    private KeyPair $keyPair;

    protected function setUp(): void
    {
        $this->service = new Nip98Validator(FakeSignatureService::accepting(), $this->createReplayGuard(), new SystemClock());
        $this->keyPair = KeyMother::alice();
    }

    public function testValidEventReturnsPublicKey(): void
    {
        $event = $this->createValidSignedEvent();

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com/', 'POST', hash('sha256', '{"method":"test"}')),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testRejectsWrongKind(): void
    {
        $event = $this->createSignedEvent(EventKind::fromInt(EventKind::TEXT_NOTE));

        $this->assertSame(
            Nip98ValidationFailure::WrongKind,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsUnsignedEvent(): void
    {
        $event = EventFactory::createHttpAuth(
            $this->keyPair->getPublicKey(),
            'https://relay.example.com/',
            'POST'
        );

        $this->assertSame(
            Nip98ValidationFailure::Unsigned,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsExpiredTimestamp(): void
    {
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 120));

        $this->assertSame(
            Nip98ValidationFailure::TimestampOutsideTolerance,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testAcceptsTimestampWithinTolerance(): void
    {
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 30));

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com/', 'POST', hash('sha256', '{"method":"test"}')),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testRejectsMissingUrlTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MissingUrlTag,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsWrongUrl(): void
    {
        $event = $this->createValidSignedEvent();

        $this->assertSame(
            Nip98ValidationFailure::UrlMismatch,
            $this->service->validate($event, Nip98Request::withBodyHash('https://other-relay.example.com/', 'POST'))
        );
    }

    public function testRejectsMissingMethodTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MissingMethodTag,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsWrongMethod(): void
    {
        $event = $this->createValidSignedEvent();

        $this->assertSame(
            Nip98ValidationFailure::MethodMismatch,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'GET'))
        );
    }

    public function testRejectsMissingPayloadTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MissingPayloadTag,
            $this->service->validate(
                $event,
                Nip98Request::withBodyHash('https://relay.example.com/', 'POST', hash('sha256', 'body')),
            )
        );
    }

    public function testRejectsWrongPayloadHash(): void
    {
        $event = $this->createValidSignedEvent();

        $this->assertSame(
            Nip98ValidationFailure::PayloadMismatch,
            $this->service->validate(
                $event,
                Nip98Request::withBodyHash('https://relay.example.com/', 'POST', hash('sha256', 'different body')),
            )
        );
    }

    public function testSkipsPayloadValidationWhenHashNotProvided(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com/', 'POST'),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testUrlNormalisationMatchesWithTrailingSlash(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com', 'GET'),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testUrlNormalisationPreservesQueryString(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/api?token=abc&page=1']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com/api?token=abc&page=1', 'GET'),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testUrlNormalisationRejectsDifferentQueryStrings(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/api?token=abc']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::UrlMismatch,
            $this->service->validate(
                $event,
                Nip98Request::withBodyHash('https://relay.example.com/api?token=xyz', 'GET'),
            )
        );
    }

    public function testMethodComparisonIsCaseInsensitive(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'post']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $result = $this->service->validate(
            $event,
            Nip98Request::withBodyHash('https://relay.example.com/', 'POST'),
        );

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testRejectsDuplicateUrlTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['u', 'https://decoy.example.com/']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MultipleUrlTags,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsDuplicateMethodTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MultipleMethodTags,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsDuplicatePayloadTag(): void
    {
        $body = '{"method":"test"}';
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'POST']),
            Tag::fromArray(['payload', hash('sha256', $body)]),
            Tag::fromArray(['payload', hash('sha256', 'something else')]),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MultiplePayloadTags,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST', hash('sha256', $body)))
        );
    }

    public function testRejectsMalformedRequestUrl(): void
    {
        $event = $this->createValidSignedEvent();

        $this->assertSame(
            Nip98ValidationFailure::MalformedUrl,
            $this->service->validate($event, Nip98Request::withBodyHash('http://:/bad', 'POST', hash('sha256', '{"method":"test"}')))
        );
    }

    public function testRejectsMalformedEventUrl(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'http://:/bad']),
            Tag::fromArray(['method', 'POST']),
        ]);
        $event = $this->createSignedEventWithTags($tags);

        $this->assertSame(
            Nip98ValidationFailure::MalformedUrl,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsReplayedAuthEvent(): void
    {
        $event = $this->createValidSignedEvent();
        $body = hash('sha256', '{"method":"test"}');

        $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST', $body));

        $this->assertSame(
            Nip98ValidationFailure::Replayed,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST', $body))
        );
    }

    public function testCustomTimestampTolerance(): void
    {
        $service = new Nip98Validator(FakeSignatureService::accepting(), $this->createReplayGuard(), new SystemClock(), timestampTolerance: 10);
        $event = $this->createSignedEventWithTimestamp(Timestamp::fromInt(time() - 30));

        $this->assertSame(
            Nip98ValidationFailure::TimestampOutsideTolerance,
            $service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testRejectsEventWithPayloadTagWhenBodyHashNotProvided(): void
    {
        $event = $this->createValidSignedEvent();

        $this->assertSame(
            Nip98ValidationFailure::PayloadTagWithoutBodyHash,
            $this->service->validate($event, Nip98Request::withBodyHash('https://relay.example.com/', 'POST'))
        );
    }

    public function testValidateAuthHeaderReturnsPublicKey(): void
    {
        $body = '{"method":"test"}';
        $event = $this->createValidSignedEvent();
        $authHeader = 'Nostr '.base64_encode((string) json_encode($event->toArray(), JSON_THROW_ON_ERROR));

        $result = $this->service->validateAuthHeader($authHeader, Nip98Request::withBody('https://relay.example.com/', 'POST', $body));

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testValidateAuthHeaderAllowsEmptyBodyWithoutPayloadTag(): void
    {
        $tags = new TagCollection([
            Tag::fromArray(['u', 'https://relay.example.com/']),
            Tag::fromArray(['method', 'GET']),
        ]);
        $event = $this->createSignedEventWithTags($tags);
        $authHeader = 'Nostr '.base64_encode((string) json_encode($event->toArray(), JSON_THROW_ON_ERROR));

        $result = $this->service->validateAuthHeader($authHeader, Nip98Request::withBody('https://relay.example.com/', 'GET', ''));

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue($result->equals($this->keyPair->getPublicKey()));
    }

    public function testValidateAuthHeaderRejectsOversizedHeader(): void
    {
        $oversized = 'Nostr '.str_repeat('A', 4096);

        $this->assertSame(
            Nip98ValidationFailure::HeaderTooLong,
            $this->service->validateAuthHeader($oversized, Nip98Request::withBody('https://relay.example.com/', 'POST', ''))
        );
    }

    public function testValidateAuthHeaderRejectsMissingPrefix(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::HeaderBadFormat,
            $this->service->validateAuthHeader('Bearer token', Nip98Request::withBody('https://relay.example.com/', 'POST', ''))
        );
    }

    public function testValidateAuthHeaderRejectsInvalidBase64(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::HeaderBadBase64,
            $this->service->validateAuthHeader('Nostr !!!not-base64!!!', Nip98Request::withBody('https://relay.example.com/', 'POST', ''))
        );
    }

    public function testValidateAuthHeaderRejectsInvalidJson(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::HeaderBadJson,
            $this->service->validateAuthHeader('Nostr '.base64_encode('not-json'), Nip98Request::withBody('https://relay.example.com/', 'POST', ''))
        );
    }

    public function testValidateAuthHeaderRejectsNonObjectJson(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::HeaderBadJson,
            $this->service->validateAuthHeader('Nostr '.base64_encode('"a string"'), Nip98Request::withBody('https://relay.example.com/', 'POST', ''))
        );
    }

    public function testValidateAuthHeaderRejectsMalformedEvent(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::HeaderInvalidEvent,
            $this->service->validateAuthHeader(
                'Nostr '.base64_encode((string) json_encode(['kind' => 27235])),
                Nip98Request::withBody('https://relay.example.com/', 'POST', ''),
            )
        );
    }

    public function testValidateAuthHeaderRejectsPayloadHashMismatch(): void
    {
        $event = $this->createValidSignedEvent();
        $authHeader = 'Nostr '.base64_encode((string) json_encode($event->toArray(), JSON_THROW_ON_ERROR));

        $this->assertSame(
            Nip98ValidationFailure::PayloadMismatch,
            $this->service->validateAuthHeader($authHeader, Nip98Request::withBody('https://relay.example.com/', 'POST', '{"different":"body"}'))
        );
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

        return $event->sign($this->keyPair, FakeSignatureService::accepting());
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
            EventKind::fromInt(EventKind::HTTP_AUTH),
            $tags,
            EventContent::empty()
        );

        return $event->sign($this->keyPair, FakeSignatureService::accepting());
    }

    private function createReplayGuard(): Nip98ReplayGuardInterface
    {
        return new class implements Nip98ReplayGuardInterface {
            /** @var array<string, int> */
            private array $seen = [];

            public function recordOnce(EventId $eventId, int $ttlSeconds): bool
            {
                $key = $eventId->toHex();
                if (isset($this->seen[$key])) {
                    return false;
                }
                $this->seen[$key] = $ttlSeconds;

                return true;
            }
        };
    }

    private function createSignedEventWithTags(TagCollection $tags): Event
    {
        $event = new Event(
            $this->keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt(EventKind::HTTP_AUTH),
            $tags,
            EventContent::empty()
        );

        return $event->sign($this->keyPair, FakeSignatureService::accepting());
    }
}
