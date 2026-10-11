<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\Collection;

use Innis\Nostr\Core\Domain\Collection\WebUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class WebUrlCollectionTest extends TestCase
{
    public function testTheConstructorRejectsAnElementOfAnotherType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WebUrlCollection([EventId::tryFromHex(hash('sha256', 'seed')) ?? self::fail('invalid fixture event id')]);
    }

    public function testFromStringsKeepsEachUrlOnceAsWrittenAndDropsWhatIsNoWebUrl(): void
    {
        $urls = WebUrlCollection::fromStrings(['https://mint.example', 'not a url', 'HTTPS://Mint.example', 'https://mint.example']);

        $this->assertSame(['https://mint.example', 'HTTPS://Mint.example'], $urls->toStrings());
    }

    public function testContainsAUrlWrittenTheSameWay(): void
    {
        $this->assertTrue(WebUrlCollection::fromStrings(['https://mint.example'])->contains(WebUrl::fromString('https://mint.example')));
    }

    public function testDoesNotContainAUrlWrittenAnotherWay(): void
    {
        $this->assertFalse(WebUrlCollection::fromStrings(['https://mint.example'])->contains(WebUrl::fromString('https://mint.example/')));
    }
}
