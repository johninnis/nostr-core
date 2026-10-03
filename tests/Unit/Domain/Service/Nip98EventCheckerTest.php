<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\Service\Nip98EventChecker;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Nip98Request;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Sha256Hash;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Tests\Fake\FakeSignatureService;
use Innis\Nostr\Core\Tests\Support\KeyMother;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class Nip98EventCheckerTest extends TestCase
{
    private const int NOW = 1_700_000_000;
    private const string URL = 'https://relay.example.com/';
    private const string BODY = '{"method":"test"}';

    public function testAConformingEventPasses(): void
    {
        $this->assertNull($this->check($this->event(self::conformingTags()), self::postWithBody(self::BODY)));
    }

    public function testTheWrongKindIsRefused(): void
    {
        $event = $this->event(self::conformingTags(), kind: EventKind::TEXT_NOTE);

        $this->assertSame(Nip98ValidationFailure::WrongKind, $this->check($event, self::postWithBody(self::BODY)));
    }

    #[DataProvider('createdAtOutsideTheDefaultTolerance')]
    public function testAnEventOutsideTheToleranceOfTheGivenInstantIsRefused(int $createdAt): void
    {
        $event = $this->event(self::conformingTags(), createdAt: $createdAt);

        $this->assertSame(Nip98ValidationFailure::TimestampOutsideTolerance, $this->check($event, self::postWithBody(self::BODY)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function createdAtOutsideTheDefaultTolerance(): iterable
    {
        yield 'one second too old' => [self::NOW - 61];
        yield 'one second too far ahead' => [self::NOW + 61];
    }

    #[DataProvider('createdAtWithinTheDefaultTolerance')]
    public function testAnEventWithinTheToleranceOfTheGivenInstantPasses(int $createdAt): void
    {
        $event = $this->event(self::conformingTags(), createdAt: $createdAt);

        $this->assertNull($this->check($event, self::postWithBody(self::BODY)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function createdAtWithinTheDefaultTolerance(): iterable
    {
        yield 'at the oldest accepted second' => [self::NOW - 60];
        yield 'at the latest accepted second' => [self::NOW + 60];
    }

    public function testACustomToleranceNarrowsTheWindow(): void
    {
        $checker = new Nip98EventChecker(FakeSignatureService::accepting(), timestampTolerance: 10);
        $event = $this->event(self::conformingTags(), createdAt: self::NOW - 11);

        $this->assertSame(Nip98ValidationFailure::TimestampOutsideTolerance, $this->check($event, self::postWithBody(self::BODY), $checker));
    }

    public function testAZeroToleranceAcceptsOnlyTheGivenInstant(): void
    {
        $checker = new Nip98EventChecker(FakeSignatureService::accepting(), timestampTolerance: 0);

        $this->assertNull($this->check($this->event(self::conformingTags()), self::postWithBody(self::BODY), $checker));
    }

    public function testANegativeToleranceIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nip98EventChecker(FakeSignatureService::accepting(), timestampTolerance: -1);
    }

    #[DataProvider('expiredTags')]
    public function testAnEventWhoseStatedExpiryHasPassedIsRefused(string ...$expirations): void
    {
        $this->assertSame(Nip98ValidationFailure::Expired, $this->check($this->eventExpiringAt(...$expirations), self::get()));
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function expiredTags(): iterable
    {
        yield 'expiry in the past' => [(string) (self::NOW - 1)];
        yield 'expiry at the given instant' => [(string) self::NOW];
        yield 'any of two expiries passed' => [(string) (self::NOW + 60), (string) (self::NOW - 1)];
    }

    #[DataProvider('unexpiredTags')]
    public function testAnEventWhoseExpiryHasNotPassedPasses(string ...$expirations): void
    {
        $this->assertNull($this->check($this->eventExpiringAt(...$expirations), self::get()));
    }

    /**
     * @return iterable<string, list<string>>
     */
    public static function unexpiredTags(): iterable
    {
        yield 'expiry in the future' => [(string) (self::NOW + 1)];
        yield 'an expiry that is not a timestamp' => ['soon'];
    }

    public function testAMissingUrlTagIsRefused(): void
    {
        $event = $this->event([['method', 'POST']]);

        $this->assertSame(Nip98ValidationFailure::MissingUrlTag, $this->check($event, self::post()));
    }

    public function testDisagreeingUrlTagsAreRefused(): void
    {
        $event = $this->event([['u', self::URL], ['u', 'https://decoy.example.com/'], ['method', 'POST']]);

        $this->assertSame(Nip98ValidationFailure::DisagreeingUrlTags, $this->check($event, self::post()));
    }

    #[DataProvider('urlTagsThatAreNotHttpUrls')]
    public function testAUrlTagThatIsNotAnHttpUrlIsRefused(string $url): void
    {
        $event = $this->event([['u', $url], ['method', 'POST']]);

        $this->assertSame(Nip98ValidationFailure::MalformedUrl, $this->check($event, self::post()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function urlTagsThatAreNotHttpUrls(): iterable
    {
        yield 'not a url' => ['garbage'];
        yield 'no host' => ['http://:/bad'];
    }

    public function testAUrlForAnotherHostIsRefused(): void
    {
        $request = Nip98Request::fromBodyHash(HttpUrl::fromString('https://other-relay.example.com/'), 'POST', Sha256Hash::ofContent(self::BODY));

        $this->assertSame(Nip98ValidationFailure::UrlMismatch, $this->check($this->event(self::conformingTags()), $request));
    }

    public function testUrlsAreComparedCanonically(): void
    {
        $event = $this->event([['u', 'https://relay.example.com'], ['method', 'GET']]);

        $this->assertNull($this->check($event, self::get()));
    }

    public function testAMatchingQueryStringPasses(): void
    {
        $event = $this->event([['u', 'https://relay.example.com/api?token=abc&page=1'], ['method', 'GET']]);
        $request = Nip98Request::fromBodyHash(HttpUrl::fromString('https://relay.example.com/api?token=abc&page=1'), 'GET');

        $this->assertNull($this->check($event, $request));
    }

    public function testADifferentQueryStringIsRefused(): void
    {
        $event = $this->event([['u', 'https://relay.example.com/api?token=abc'], ['method', 'GET']]);
        $request = Nip98Request::fromBodyHash(HttpUrl::fromString('https://relay.example.com/api?token=xyz'), 'GET');

        $this->assertSame(Nip98ValidationFailure::UrlMismatch, $this->check($event, $request));
    }

    public function testAMissingMethodTagIsRefused(): void
    {
        $event = $this->event([['u', self::URL]]);

        $this->assertSame(Nip98ValidationFailure::MissingMethodTag, $this->check($event, self::post()));
    }

    public function testDisagreeingMethodTagsAreRefused(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'POST'], ['method', 'GET']]);

        $this->assertSame(Nip98ValidationFailure::DisagreeingMethodTags, $this->check($event, self::post()));
    }

    public function testIdenticalRepeatedUrlAndMethodTagsAreOneClaim(): void
    {
        $event = $this->event([['u', self::URL], ['u', self::URL], ['method', 'POST'], ['method', 'POST']]);

        $this->assertNull($this->check($event, self::post()));
    }

    public function testAnotherMethodIsRefused(): void
    {
        $this->assertSame(Nip98ValidationFailure::MethodMismatch, $this->check($this->event(self::conformingTags()), self::get()));
    }

    public function testTheMethodIsComparedCaseSensitively(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'post']]);

        $this->assertSame(Nip98ValidationFailure::MethodMismatch, $this->check($event, self::post()));
    }

    public function testABodyWithoutAPayloadTagIsRefused(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'POST']]);

        $this->assertSame(Nip98ValidationFailure::MissingPayloadTag, $this->check($event, self::postWithBody('body')));
    }

    public function testAPayloadTagForAnotherBodyIsRefused(): void
    {
        $this->assertSame(
            Nip98ValidationFailure::PayloadMismatch,
            $this->check($this->event(self::conformingTags()), self::postWithBody('different body')),
        );
    }

    public function testDisagreeingPayloadTagsAreRefused(): void
    {
        $event = $this->event([
            ['u', self::URL],
            ['method', 'POST'],
            ['payload', hash('sha256', self::BODY)],
            ['payload', hash('sha256', 'something else')],
        ]);

        $this->assertSame(Nip98ValidationFailure::DisagreeingPayloadTags, $this->check($event, self::postWithBody(self::BODY)));
    }

    public function testAnUppercaseHexPayloadTagForTheRightBodyIsRefused(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'POST'], ['payload', strtoupper(hash('sha256', 'body'))]]);

        $this->assertSame(Nip98ValidationFailure::PayloadMismatch, $this->check($event, self::postWithBody('body')));
    }

    public function testALowercaseHexPayloadTagForTheRightBodyPasses(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'POST'], ['payload', hash('sha256', 'body')]]);

        $this->assertNull($this->check($event, self::postWithBody('body')));
    }

    public function testNoBodyHashPassesAnEventWithoutAPayloadTag(): void
    {
        $this->assertNull($this->check($this->event([['u', self::URL], ['method', 'POST']]), self::post()));
    }

    public function testNoBodyHashRefusesAnEventWithAPayloadTag(): void
    {
        $this->assertSame(Nip98ValidationFailure::PayloadTagWithoutBodyHash, $this->check($this->event(self::conformingTags()), self::post()));
    }

    public function testTheEmptyBodysHashIsNoBody(): void
    {
        $request = Nip98Request::fromBodyHash(HttpUrl::fromString(self::URL), 'GET', Sha256Hash::ofContent(''));

        $this->assertNull($this->check($this->event([['u', self::URL], ['method', 'GET']]), $request));
    }

    public function testAPayloadTagCarryingTheEmptyBodysHashIsRefused(): void
    {
        $event = $this->event([['u', self::URL], ['method', 'GET'], ['payload', hash('sha256', '')]]);
        $request = Nip98Request::fromBodyHash(HttpUrl::fromString(self::URL), 'GET', Sha256Hash::ofContent(''));

        $this->assertSame(Nip98ValidationFailure::PayloadTagWithoutBodyHash, $this->check($event, $request));
    }

    public function testABadSignatureIsRefusedOnceEveryRequestCheckPasses(): void
    {
        $checker = new Nip98EventChecker(FakeSignatureService::rejecting());

        $this->assertSame(
            Nip98ValidationFailure::BadSignature,
            $this->check($this->event(self::conformingTags()), self::postWithBody(self::BODY), $checker),
        );
    }

    public function testAPayloadMismatchIsReportedBeforeTheSignatureIsChecked(): void
    {
        $checker = new Nip98EventChecker(FakeSignatureService::rejecting());

        $this->assertSame(
            Nip98ValidationFailure::PayloadMismatch,
            $this->check($this->event(self::conformingTags()), self::postWithBody('different body'), $checker),
        );
    }

    public function testAMissingPayloadTagIsReportedBeforeTheSignatureIsChecked(): void
    {
        $checker = new Nip98EventChecker(FakeSignatureService::rejecting());
        $event = $this->event([['u', self::URL], ['method', 'POST']]);

        $this->assertSame(Nip98ValidationFailure::MissingPayloadTag, $this->check($event, self::postWithBody('body'), $checker));
    }

    // Deliberate: the timestamp window is inclusive at both ends, so it spans 2 * tolerance + 1 whole seconds and the replay record must outlive every one of them — see ADR-0110
    public function testTheReplayWindowSpansTheInclusiveTimestampWindow(): void
    {
        $this->assertSame(121, new Nip98EventChecker(FakeSignatureService::accepting(), timestampTolerance: 60)->getReplayWindowSeconds());
    }

    public function testAZeroToleranceHasAOneSecondReplayWindow(): void
    {
        $this->assertSame(1, new Nip98EventChecker(FakeSignatureService::accepting(), timestampTolerance: 0)->getReplayWindowSeconds());
    }

    private function check(Event $event, Nip98Request $request, ?Nip98EventChecker $checker = null): ?Nip98ValidationFailure
    {
        return ($checker ?? new Nip98EventChecker(FakeSignatureService::accepting()))->check($event, $request, Timestamp::fromInt(self::NOW));
    }

    /**
     * @return list<list<string>>
     */
    private static function conformingTags(): array
    {
        return [['u', self::URL], ['method', 'POST'], ['payload', hash('sha256', self::BODY)]];
    }

    private static function post(): Nip98Request
    {
        return Nip98Request::fromBodyHash(HttpUrl::fromString(self::URL), 'POST');
    }

    private static function postWithBody(string $body): Nip98Request
    {
        return Nip98Request::fromBody(HttpUrl::fromString(self::URL), 'POST', $body);
    }

    private static function get(): Nip98Request
    {
        return Nip98Request::fromBodyHash(HttpUrl::fromString(self::URL), 'GET');
    }

    private function eventExpiringAt(string ...$expirations): Event
    {
        return $this->event([
            ['u', self::URL],
            ['method', 'GET'],
            ...array_map(static fn (string $expiration): array => ['expiration', $expiration], array_values($expirations)),
        ]);
    }

    /**
     * @param list<list<string>> $tags
     */
    private function event(array $tags, int $createdAt = self::NOW, int $kind = EventKind::HTTP_AUTH): Event
    {
        $keyPair = KeyMother::alice();

        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::empty(),
            new TagCollection(array_map(Tag::fromArray(...), $tags)),
            Timestamp::fromInt($createdAt),
        )->sign($keyPair, FakeSignatureService::accepting());
    }
}
