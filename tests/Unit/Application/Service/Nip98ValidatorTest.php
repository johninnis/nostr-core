<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Application\Service;

use Innis\Nostr\Core\Application\Port\ClockInterface;
use Innis\Nostr\Core\Application\Port\Nip98ReplayGuardInterface;
use Innis\Nostr\Core\Application\Service\Nip98Validator;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\AuthHeaderDecodeFailure;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip98EventChecker;
use Innis\Nostr\Core\Domain\Service\Nip98EventCheckerInterface;
use Innis\Nostr\Core\Domain\Service\NostrAuthHeaderCodec;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use Override;
use PHPUnit\Framework\TestCase;

final class Nip98ValidatorTest extends TestCase
{
    private const int NOW = 1_700_000_000;
    private const string URL = 'https://relay.example.com/';

    public function testChecksTheEventAtTheClocksInstant(): void
    {
        $event = $this->authEvent(self::NOW);
        $request = self::request();
        $checker = $this->createMock(Nip98EventCheckerInterface::class);
        $checker->expects($this->once())
            ->method('check')
            ->with($event, $request, Timestamp::fromInt(self::NOW))
            ->willReturn(null);

        new Nip98Validator($checker, $this->replayGuard(), $this->clockAt(self::NOW))->validate($event, $request);
    }

    public function testReturnsTheFailureItsCheckerReports(): void
    {
        $checker = $this->createStub(Nip98EventCheckerInterface::class);
        $checker->method('check')->willReturn(Nip98ValidationFailure::UrlMismatch);

        $this->assertSame(
            Nip98ValidationFailure::UrlMismatch,
            new Nip98Validator($checker, $this->replayGuard(), $this->clockAt(self::NOW))->validate($this->authEvent(self::NOW), self::request()),
        );
    }

    public function testDoesNotRecordAnEventItsCheckerRefuses(): void
    {
        $checker = $this->createStub(Nip98EventCheckerInterface::class);
        $checker->method('check')->willReturn(Nip98ValidationFailure::BadSignature);
        $guard = $this->createMock(Nip98ReplayGuardInterface::class);
        $guard->expects($this->never())->method('recordOnce');

        new Nip98Validator($checker, $guard, $this->clockAt(self::NOW))->validate($this->authEvent(self::NOW), self::request());
    }

    public function testRecordsAnAcceptedEventForItsCheckersReplayWindow(): void
    {
        $event = $this->authEvent(self::NOW);
        $checker = $this->createStub(Nip98EventCheckerInterface::class);
        $checker->method('check')->willReturn(null);
        $checker->method('getReplayWindowSeconds')->willReturn(21);
        $guard = $this->createMock(Nip98ReplayGuardInterface::class);
        $guard->expects($this->once())->method('recordOnce')->with($event->getId(), 21)->willReturn(true);

        new Nip98Validator($checker, $guard, $this->clockAt(self::NOW))->validate($event, self::request());
    }

    public function testAnAcceptedEventReturnsItsAuthor(): void
    {
        $result = $this->validator()->validate($this->authEvent(self::NOW), self::request());

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue(KeyMother::alicePublicKey()->equals($result));
    }

    public function testAnEventPresentedTwiceIsRefusedAsReplayed(): void
    {
        $validator = $this->validator();
        $event = $this->authEvent(self::NOW);
        $validator->validate($event, self::request());

        $this->assertSame(Nip98ValidationFailure::Replayed, $validator->validate($event, self::request()));
    }

    public function testAnEventFromBeyondTheToleranceOfTheClockIsRefused(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::TimestampOutsideTolerance,
            $this->validator()->validate($this->authEvent(self::NOW - 61), self::request()),
        );
    }

    public function testValidatesTheEventAnAuthHeaderCarries(): void
    {
        $header = NostrAuthHeaderCodec::encode($this->authEvent(self::NOW)) ?? self::fail('Expected an encodable header');

        $result = $this->validator()->validateAuthHeader($header, self::request());

        $this->assertInstanceOf(PublicKey::class, $result);
        $this->assertTrue(KeyMother::alicePublicKey()->equals($result));
    }

    public function testReturnsTheDecodeFailureOfAnAuthHeader(): void
    {
        $this->assertSame(AuthHeaderDecodeFailure::BadFormat, $this->validator()->validateAuthHeader('Bearer token', self::request()));
    }

    private function validator(): Nip98Validator
    {
        return new Nip98Validator(new Nip98EventChecker(FakeSignatureService::accepting()), $this->replayGuard(), $this->clockAt(self::NOW));
    }

    private static function request(): Nip98Request
    {
        return Nip98Request::fromBodyHash(HttpUrl::fromString(self::URL), 'GET');
    }

    private function clockAt(int $now): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(Timestamp::fromInt($now));

        return $clock;
    }

    private function replayGuard(): Nip98ReplayGuardInterface
    {
        return new class implements Nip98ReplayGuardInterface {
            /** @var array<string, true> */
            private array $seen = [];

            #[Override]
            public function recordOnce(EventId $eventId, int $ttlSeconds): bool
            {
                if (isset($this->seen[$eventId->toHex()])) {
                    return false;
                }
                $this->seen[$eventId->toHex()] = true;

                return true;
            }
        };
    }

    private function authEvent(int $createdAt): Event
    {
        $keyPair = KeyMother::alice();

        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt(EventKind::HTTP_AUTH),
            EventContent::empty(),
            new TagCollection([Tag::fromArray(['u', self::URL]), Tag::fromArray(['method', 'GET'])]),
            Timestamp::fromInt($createdAt),
        )->sign($keyPair, FakeSignatureService::accepting());
    }
}
