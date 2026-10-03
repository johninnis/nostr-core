<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NostrAuthHeaderCodecTest extends TestCase
{
    public function testTheSchemeTokenIsMatchedWithoutRegardToCase(): void
    {
        $header = base64_encode('not an event');

        $this->assertSame(
            NostrAuthHeaderCodec::decode('Nostr '.$header),
            NostrAuthHeaderCodec::decode('nostr '.$header),
        );
        $this->assertSame(
            NostrAuthHeaderCodec::decode('Nostr '.$header),
            NostrAuthHeaderCodec::decode('NOSTR '.$header),
        );
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $event = $this->signedEvent();

        $decoded = NostrAuthHeaderCodec::decode(NostrAuthHeaderCodec::encode($event) ?? '');

        self::assertInstanceOf(Event::class, $decoded);
        self::assertTrue($decoded->getId()->equals($event->getId()));
    }

    public function testRejectsOversizeHeader(): void
    {
        $header = NostrAuthHeaderCodec::HEADER_PREFIX.str_repeat('A', NostrAuthHeaderCodec::MAX_HEADER_LENGTH);

        self::assertSame(AuthHeaderDecodeFailure::TooLong, NostrAuthHeaderCodec::decode($header));
    }

    public function testRefusesToEncodeAHeaderDecodeWouldRefuseAsTooLong(): void
    {
        $this->assertNull(NostrAuthHeaderCodec::encode($this->signedEvent(str_repeat('a', NostrAuthHeaderCodec::MAX_HEADER_LENGTH))));
    }

    public function testEncodesTheLongestHeaderDecodeAccepts(): void
    {
        $emptyLength = strlen(NostrAuthHeaderCodec::encode($this->signedEvent()) ?? '');
        $longestContent = str_repeat('a', intdiv(NostrAuthHeaderCodec::MAX_HEADER_LENGTH - $emptyLength, 4) * 3);

        self::assertInstanceOf(Event::class, NostrAuthHeaderCodec::decode(NostrAuthHeaderCodec::encode($this->signedEvent($longestContent)) ?? ''));
    }

    public function testRejectsMissingPrefix(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadFormat, NostrAuthHeaderCodec::decode('Bearer token'));
    }

    public function testRejectsInvalidBase64(): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadBase64, NostrAuthHeaderCodec::decode('Nostr !!!not-base64!!!'));
    }

    #[DataProvider('nonCanonicalBase64')]
    public function testReadsEverythingAfterTheSchemeExactlyAsCanonicalBase64(string $afterScheme): void
    {
        self::assertSame(AuthHeaderDecodeFailure::BadBase64, NostrAuthHeaderCodec::decode('Nostr '.$afterScheme));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonCanonicalBase64(): iterable
    {
        $encoded = base64_encode('{"kind":27235}');

        yield 'a second space after the scheme' => [' '.$encoded];
        yield 'a trailing space' => [$encoded.' '];
        yield 'a line break inside' => [substr($encoded, 0, 4)."\n".substr($encoded, 4)];
        yield 'padding left off' => ['YQ'];
        yield 'non-zero padding bits' => ['YR=='];
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

    public function testRejectsAnEventWhoseTagsAreWrittenAsAnObject(): void
    {
        $json = str_replace('"tags":[]', '"tags":{}', $this->signedEvent()->toJson());

        self::assertSame(AuthHeaderDecodeFailure::InvalidEvent, NostrAuthHeaderCodec::decode('Nostr '.base64_encode($json)));
    }

    public function testDecodesAnEventCarryingADeeplyNestedExtraField(): void
    {
        $event = $this->signedEvent();
        $json = substr($event->toJson(), 0, -1).',"extra":'.str_repeat('[', 40).str_repeat(']', 40).'}';

        $this->assertEquals($event, NostrAuthHeaderCodec::decode(NostrAuthHeaderCodec::HEADER_PREFIX.base64_encode($json)));
    }

    private function signedEvent(string $content = ''): Event
    {
        $keyPair = KeyMother::alice();

        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::HTTP_AUTH),
            EventContent::fromString($content),
            new TagCollection([]),
        )->sign($keyPair, FakeSignatureService::accepting());
    }
}
