<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\CountMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CountMessageTest extends TestCase
{
    private const int LARGE_FILTER_COUNT = 100;

    public function testGetTypeReturnsCount(): void
    {
        $message = CountMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]),
        );

        $this->assertSame(ClientMessageType::Count, $message->type());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = CountMessage::from($subId, new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]));

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetFiltersReturnsConstructedFilters(): void
    {
        $filter = Filter::from(kinds: EventKindCollection::fromInts([1]));
        $message = CountMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $this->assertCount(1, $message->getFilters());
        $this->assertSame($filter, $message->getFilters()->toArray()[0]);
    }

    public function testConstructorThrowsOnEmptyFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('COUNT message must carry at least one filter');

        CountMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([]));
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
        $message = CountMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $result = $message->toArray();

        $this->assertSame('COUNT', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame($filter->toArray(), $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToArrayWithMultipleFilters(): void
    {
        $filter1 = Filter::from(kinds: EventKindCollection::fromInts([1]));
        $filter2 = Filter::from(kinds: EventKindCollection::fromInts([0]), limit: 10);
        $message = CountMessage::from(SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter1, $filter2]));

        $result = $message->toArray();

        $this->assertCount(4, $result);
        $this->assertSame($filter1->toArray(), $result[2]);
        $this->assertSame($filter2->toArray(), $result[3]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = CountMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1]))]),
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('COUNT', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
    }

    public function testTryFromArrayCreatesValidMessage(): void
    {
        $data = ['COUNT', 'sub-1', ['kinds' => [1]]];

        $message = CountMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(ClientMessageType::Count, $message->type());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertCount(1, $message->getFilters());
    }

    public function testTryFromArrayWithMultipleFilters(): void
    {
        $data = ['COUNT', 'sub-1', ['kinds' => [1]], ['kinds' => [0]]];

        $message = CountMessage::tryFromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertCount(2, $message->getFilters());
    }

    public function testTryFromArrayReturnsNullOnInvalidFormat(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['COUNT', 'sub-1']));
    }

    public function testTryFromArrayReturnsNullOnWrongType(): void
    {
        $this->assertNull(CountMessage::tryFromArray(['REQ', 'sub-1', ['kinds' => [1]]]));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = CountMessage::from(
            SubscriptionId::tryFromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([Filter::from(kinds: EventKindCollection::fromInts([1])), Filter::from(limit: 50)]),
        );

        $restored = CountMessage::tryFromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertCount(count($original->getFilters()), $restored->getFilters());
    }

    public function testTryFromArrayKeepsEveryFilterOfALargeRequest(): void
    {
        $payload = ['COUNT', 'sub-1', ...array_fill(0, self::LARGE_FILTER_COUNT, ['kinds' => [1]])];

        $this->assertCount(self::LARGE_FILTER_COUNT, CountMessage::tryFromArray($payload)?->getFilters() ?? []);
    }

    public function testTryFromKeepsAFilterThatCannotMatch(): void
    {
        $unmatchable = Filter::from(kinds: new EventKindCollection());

        $message = CountMessage::tryFrom(SubscriptionId::generate(), new FilterCollection([$unmatchable]));

        $this->assertSame([$unmatchable], $message?->getFilters()->toArray());
    }

    public function testTryFromArrayKeepsEveryFilterAsReceived(): void
    {
        $message = CountMessage::tryFromArray(['COUNT', 'sub-1', ['authors' => []], ['kinds' => [1]], ['since' => 2, 'until' => 1]]);

        $this->assertSame(
            [['authors' => []], ['kinds' => [1]], ['since' => 2, 'until' => 1]],
            array_map(static fn (Filter $filter): array => $filter->toArray(), $message?->getFilters()->toArray() ?? []),
        );
    }
}
