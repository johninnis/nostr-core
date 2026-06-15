<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\AuthHeaderDecodeError;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Tests\Support\WithCryptoServices;
use PHPUnit\Framework\TestCase;

final class NostrAuthHeaderCodecTest extends TestCase
{
    use WithCryptoServices;

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

        self::assertSame(AuthHeaderDecodeError::TooLong, NostrAuthHeaderCodec::decode($header));
    }

    public function testRejectsMissingPrefix(): void
    {
        self::assertSame(AuthHeaderDecodeError::BadFormat, NostrAuthHeaderCodec::decode('Bearer token'));
    }

    public function testRejectsInvalidBase64(): void
    {
        self::assertSame(AuthHeaderDecodeError::BadBase64, NostrAuthHeaderCodec::decode('Nostr !!!not-base64!!!'));
    }

    public function testRejectsNonJsonPayload(): void
    {
        self::assertSame(AuthHeaderDecodeError::BadJson, NostrAuthHeaderCodec::decode('Nostr '.base64_encode('not-json')));
    }

    public function testRejectsNonObjectJsonPayload(): void
    {
        self::assertSame(AuthHeaderDecodeError::BadJson, NostrAuthHeaderCodec::decode('Nostr '.base64_encode('"a string"')));
    }

    public function testRejectsMalformedEvent(): void
    {
        $header = 'Nostr '.base64_encode((string) json_encode(['kind' => 27235]));

        self::assertSame(AuthHeaderDecodeError::InvalidEvent, NostrAuthHeaderCodec::decode($header));
    }

    private function signedEvent(): Event
    {
        $keyPair = KeyPair::generate($this->signatureService());

        return EventFactory::createCustomKind(
            $keyPair->getPublicKey(),
            EventKind::httpAuth(),
            new EventContent(''),
            new TagCollection([]),
        )->sign($keyPair, $this->signatureService());
    }
}
