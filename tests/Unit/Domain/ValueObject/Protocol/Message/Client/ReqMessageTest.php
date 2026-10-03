<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReqMessageTest extends TestCase
{
    private const int LARGE_FILTER_COUNT = 100;

    public function testGetTypeReturnsReq(): void
    {
        $message = ReqMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]),
        );

        $this->assertSame(ClientMessageType::Req, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = ReqMessage::from($subId, new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]));

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetFiltersReturnsConstructedFilters(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));
        $message = ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $this->assertCount(1, $message->getFilters());
        $this->assertSame($filter, $message->getFilters()->toArray()[0]);
    }

    public function testConstructorThrowsOnEmptyFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('REQ message must carry at least one filter');

        ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([]));
    }

    public function testTryFromRefusesAnEmptyFilterCollection(): void
    {
        $this->assertNull(ReqMessage::tryFrom(SubscriptionId::generate(), new FilterCollection()));
    }

    public function testConstructorThrowsOnNonFilterInstances(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter instances');

        new FilterCollection(['not-a-filter']);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));
        $message = ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $result = $message->toArray();

        $this->assertSame('REQ', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame($filter->toArray(), $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToArrayWithMultipleFilters(): void
    {
        $filter1 = Filter::from(kinds: EventKindCollection::fromInts([1]));
        $filter2 = Filter::from(kinds: EventKindCollection::fromInts([0]), limit: 10);
        $message = ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter1, $filter2]));

        $result = $message->toArray();

        $this->assertCount(4, $result);
        $this->assertSame($filter1->toArray(), $result[2]);
        $this->assertSame($filter2->toArray(), $result[3]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = ReqMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]),
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('REQ', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
    }

    public function testEmptyFilterSerialisesAsAJsonObjectOnTheWire(): void
    {
        $message = ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([Filter::from()]));

        $this->assertSame('["REQ","sub-1",{}]', $message->toJson());
    }

    public function testToJsonWritesTheLineAndParagraphSeparatorsVerbatim(): void
    {
        $message = ReqMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([Filter::from(search: "a\u{2028}b\u{2029}c")]));

        $this->assertSame("[\"REQ\",\"sub-1\",{\"search\":\"a\u{2028}b\u{2029}c\"}]", $message->toJson());
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $data = ['REQ', 'sub-1', ['kinds' => [1]]];

        $message = ReqMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ClientMessageType::Req, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertCount(1, $message->getFilters());
    }

    public function testTryFromArrayWithMultipleFilters(): void
    {
        $data = ['REQ', 'sub-1', ['kinds' => [1]], ['kinds' => [0]]];

        $message = ReqMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertCount(2, $message->getFilters());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(ReqMessage::tryFromArray(['REQ', 'sub-1']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(ReqMessage::tryFromArray(['CLOSE', 'sub-1', ['kinds' => [1]]]));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = ReqMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1])), Filter::from(limit: 50)]),
        );

        $restored = ReqMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertCount(count($original->getFilters()), $restored->getFilters());
    }

    public function testFromKeepsEveryFilterOfALargeRequest(): void
    {
        $filters = array_fill(0, self::LARGE_FILTER_COUNT, Filter::from(kinds: EventKindCollection::fromInts([1])));

        $message = ReqMessage::from(SubscriptionId::generate(), new FilterCollection($filters));

        $this->assertCount(self::LARGE_FILTER_COUNT, $message->getFilters());
    }

    public function testTryFromArrayKeepsEveryFilterOfALargeRequest(): void
    {
        $payload = ['REQ', 'sub-1', ...array_fill(0, self::LARGE_FILTER_COUNT, ['kinds' => [1]])];

        $this->assertCount(self::LARGE_FILTER_COUNT, ReqMessage::tryFromArray($payload)?->getFilters() ?? []);
    }

    public function testTryFromKeepsAFilterThatCannotMatch(): void
    {
        $unmatchable = Filter::from(kinds: new EventKindCollection());

        $message = ReqMessage::tryFrom(SubscriptionId::generate(), new FilterCollection([$unmatchable]));

        $this->assertSame([$unmatchable], $message?->getFilters()->toArray());
    }

    public function testTryFromArrayKeepsEveryFilterAsReceived(): void
    {
        $message = ReqMessage::tryFromArray(['REQ', 'sub-1', ['authors' => []], ['kinds' => [1]], ['since' => 2, 'until' => 1]]);

        $this->assertSame(
            [['authors' => []], ['kinds' => [1]], ['since' => 2, 'until' => 1]],
            array_map(static fn (Filter $filter): array => $filter->toArray(), $message?->getFilters()->toArray() ?? []),
        );
    }

    public function testTryFromJsonRefusesAFilterGivenAsAnEmptyList(): void
    {
        $this->assertNull(ReqMessage::tryFromJson('["REQ","s",[]]'));
    }

    public function testTryFromJsonReadsAnEmptyObjectAsTheFilterThatMatchesEverything(): void
    {
        $message = ReqMessage::tryFromJson('["REQ","s",{}]') ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('["REQ","s",{}]', $message->toJson());
    }

    public function testTryFromArrayRefusesAFilterGivenAsAList(): void
    {
        $this->assertNull(ReqMessage::tryFromArray(['REQ', 's', []]));
    }

    public function testTryFromJsonRefusesAPubkeyTagConditionThatIsNotLowercaseHex(): void
    {
        $this->assertNull(ReqMessage::tryFromJson('["REQ","s",{"#p":["zz"]}]'));
    }
}
