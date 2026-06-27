<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Collection\EventKindCollection;
use Innis\Nostr\Core\Domain\Collection\FilterCollection;
use Innis\Nostr\Core\Domain\Entity\Filter;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client\ReqMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReqMessageTest extends TestCase
{
    public function testGetTypeReturnsReq(): void
    {
        $message = new ReqMessage(
            SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([new Filter(kinds: EventKindCollection::fromInts([1]))]),
        );

        $this->assertSame('REQ', $message->getType());
    }

    public function testGetSubscriptionIdReturnsConstructedValue(): void
    {
        $subId = SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID');
        $message = new ReqMessage($subId, new FilterCollection([new Filter(kinds: EventKindCollection::fromInts([1]))]));

        $this->assertTrue($subId->equals($message->getSubscriptionId()));
    }

    public function testGetFiltersReturnsConstructedFilters(): void
    {
        $filter = new Filter(kinds: EventKindCollection::fromInts([1]));
        $message = new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $this->assertCount(1, $message->getFilters());
        $this->assertSame($filter, $message->getFilters()->toArray()[0]);
    }

    public function testConstructorThrowsOnEmptyFilters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('REQ message must have at least one filter');

        new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([]));
    }

    public function testConstructorThrowsOnNonFilterInstances(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be Innis\Nostr\Core\Domain\Entity\Filter instances');

        new FilterCollection(['not-a-filter']);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $filter = new Filter(kinds: EventKindCollection::fromInts([1]));
        $message = new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter]));

        $result = $message->toArray();

        $this->assertSame('REQ', $result[0]);
        $this->assertSame('sub-1', $result[1]);
        $this->assertSame($filter->toArray(), $result[2]);
        $this->assertCount(3, $result);
    }

    public function testToArrayWithMultipleFilters(): void
    {
        $filter1 = new Filter(kinds: EventKindCollection::fromInts([1]));
        $filter2 = new Filter(kinds: EventKindCollection::fromInts([0]), limit: 10);
        $message = new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([$filter1, $filter2]));

        $result = $message->toArray();

        $this->assertCount(4, $result);
        $this->assertSame($filter1->toArray(), $result[2]);
        $this->assertSame($filter2->toArray(), $result[3]);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $message = new ReqMessage(
            SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([new Filter(kinds: EventKindCollection::fromInts([1]))]),
        );

        $decoded = json_decode($message->toJson(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $this->assertSame('REQ', $decoded[0]);
        $this->assertSame('sub-1', $decoded[1]);
    }

    public function testEmptyFilterSerialisesAsAJsonObjectOnTheWire(): void
    {
        $message = new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection([new Filter()]));

        $this->assertSame('["REQ","sub-1",{}]', $message->toJson());
    }

    public function testFromArrayCreatesValidMessage(): void
    {
        $data = ['REQ', 'sub-1', ['kinds' => [1]]];

        $message = ReqMessage::fromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame('REQ', $message->getType());
        $this->assertSame('sub-1', (string) $message->getSubscriptionId());
        $this->assertCount(1, $message->getFilters());
    }

    public function testFromArrayWithMultipleFilters(): void
    {
        $data = ['REQ', 'sub-1', ['kinds' => [1]], ['kinds' => [0]]];

        $message = ReqMessage::fromArray($data) ?? throw new RuntimeException('Expected a valid message');

        $this->assertCount(2, $message->getFilters());
    }

    public function testFromArrayThrowsOnInvalidFormat(): void
    {
        $this->assertNull(ReqMessage::fromArray(['REQ', 'sub-1']));
    }

    public function testFromArrayThrowsOnWrongType(): void
    {
        $this->assertNull(ReqMessage::fromArray(['CLOSE', 'sub-1', ['kinds' => [1]]]));
    }

    public function testRoundTripPreservesData(): void
    {
        $original = new ReqMessage(
            SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'),
            new FilterCollection([new Filter(kinds: EventKindCollection::fromInts([1])), new Filter(limit: 50)]),
        );

        $restored = ReqMessage::fromArray($original->toArray()) ?? throw new RuntimeException('Expected a valid message');

        $this->assertSame(
            (string) $original->getSubscriptionId(),
            (string) $restored->getSubscriptionId()
        );
        $this->assertCount(count($original->getFilters()), $restored->getFilters());
    }

    public function testConstructorRejectsMoreThanMaxFilters(): void
    {
        $filters = array_fill(0, ReqMessage::MAX_FILTERS + 1, new Filter(kinds: EventKindCollection::fromInts([1])));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('may contain at most');

        new ReqMessage(SubscriptionId::fromString('sub-1') ?? throw new RuntimeException('Expected a valid subscription ID'), new FilterCollection($filters));
    }

    public function testFromArrayRejectsMoreThanMaxFilters(): void
    {
        $payload = ['REQ', 'sub-1'];
        for ($i = 0; $i < ReqMessage::MAX_FILTERS + 1; ++$i) {
            $payload[] = ['kinds' => [1]];
        }

        $this->assertNull(ReqMessage::fromArray($payload));
    }
}
