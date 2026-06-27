<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\TestCase;

final class NostrAuthHeaderCodecTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $event = $this->signedEvent();

        $decoded = NostrAuthHeaderCodec::decode(NostrAuthHeaderCodec::encode($event));

        self::assertInstanceOf(Event::class, $decoded);
        self::assertTrue($decoded->getId()->equals($event->getId()));
    }

    public function testRejectsOversizeHeader(): void
    {
        $header = NostrAuthHeaderCodec::HEADER_PREFIX.str_repeat('A', NostrAuthHeaderCodec::MAX_HEADER_LENGTH);

        self::assertSame(AuthHeaderDecodeFailure::TooLong, NostrAuthHeaderCodec::decode($header));
    }

    public function testRejectsMissingPrefix(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadFormat, NostrAuthHeaderCodec::decode('Bearer token'));
    }

    public function testRejectsInvalidBase64(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadBase64, NostrAuthHeaderCodec::decode('Nostr !!!not-base64!!!'));
    }

    public function testRejectsNonJsonPayload(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadJson, NostrAuthHeaderCodec::decode('Nostr '.base64_encode('not-json')));
    }

    public function testRejectsNonObjectJsonPayload(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadJson, NostrAuthHeaderCodec::decode('Nostr '.base64_encode('"a string"')));
    }

    public function testRejectsMalformedEvent(): void
    {
        $header = 'Nostr '.base64_encode((string) json_encode(['kind' => 27235]));

        self::assertSame(AuthHeaderDecodeFailure::InvalidEvent, NostrAuthHeaderCodec::decode($header));
    }

    private function signedEvent(): Event
    {
        $keyPair = KeyMother::alice();

        return EventFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::HTTP_AUTH),
            EventContent::empty(),
            new TagCollection([]),
        )->sign($keyPair, FakeSignatureService::accepting());
    }
}
