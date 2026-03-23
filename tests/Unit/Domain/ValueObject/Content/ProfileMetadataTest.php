<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Unit\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\ValueObject\Content\ProfileMetadata;
use PHPUnit\Framework\TestCase;

final class ProfileMetadataTest extends TestCase
{
    public function testFromJsonStringWithAllFields(): void
    {
        $json = json_encode([
            'name' => 'alice',
            'display_name' => 'Alice',
            'about' => 'A nostr user',
            'picture' => 'https://example.com/avatar.jpg',
            'banner' => 'https://example.com/banner.jpg',
            'website' => 'https://alice.example.com',
            'nip05' => 'alice@example.com',
            'lud16' => 'alice@walletofsatoshi.com',
        ], JSON_THROW_ON_ERROR);

        $metadata = ProfileMetadata::fromJsonString($json);

        $this->assertSame('alice', $metadata->getName());
        $this->assertSame('Alice', $metadata->getDisplayName());
        $this->assertSame('A nostr user', $metadata->getAbout());
        $this->assertSame('https://example.com/avatar.jpg', $metadata->getPicture());
        $this->assertSame('https://example.com/banner.jpg', $metadata->getBanner());
        $this->assertSame('https://alice.example.com', $metadata->getWebsite());
        $this->assertSame('alice@example.com', $metadata->getNip05());
        $this->assertSame('alice@walletofsatoshi.com', $metadata->getLud16());
    }

    public function testFromJsonStringWithMinimalFields(): void
    {
        $json = json_encode(['name' => 'bob'], JSON_THROW_ON_ERROR);

        $metadata = ProfileMetadata::fromJsonString($json);

        $this->assertSame('bob', $metadata->getName());
        $this->assertNull($metadata->getDisplayName());
        $this->assertNull($metadata->getAbout());
        $this->assertNull($metadata->getPicture());
        $this->assertNull($metadata->getBanner());
        $this->assertNull($metadata->getWebsite());
        $this->assertNull($metadata->getNip05());
        $this->assertNull($metadata->getLud16());
    }

    public function testFromInvalidJsonReturnsEmptyMetadata(): void
    {
        $metadata = ProfileMetadata::fromJsonString('not valid json');

        $this->assertNull($metadata->getName());
        $this->assertNull($metadata->getDisplayName());
        $this->assertNull($metadata->getAbout());
        $this->assertNull($metadata->getPicture());
        $this->assertNull($metadata->getBanner());
        $this->assertNull($metadata->getWebsite());
        $this->assertNull($metadata->getNip05());
        $this->assertNull($metadata->getLud16());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        $original = new ProfileMetadata(
            'alice',
            'Alice',
            'A nostr user',
            'https://example.com/avatar.jpg',
            'https://example.com/banner.jpg',
            'https://alice.example.com',
            'alice@example.com',
            'alice@walletofsatoshi.com',
        );

        $array = $original->toArray();
        $restored = ProfileMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testToArrayFromArrayRoundTripWithNulls(): void
    {
        $original = new ProfileMetadata(null, null, null, null, null, null, null, null);

        $array = $original->toArray();
        $restored = ProfileMetadata::fromArray($array);

        $this->assertTrue($original->equals($restored));
    }

    public function testEquals(): void
    {
        $a = new ProfileMetadata('alice', 'Alice', null, null, null, null, null, null);
        $b = new ProfileMetadata('alice', 'Alice', null, null, null, null, null, null);
        $c = new ProfileMetadata('bob', 'Bob', null, null, null, null, null, null);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    public function testNonStringValuesIgnored(): void
    {
        $json = json_encode([
            'name' => 'alice',
            'display_name' => 42,
            'about' => true,
            'picture' => ['url' => 'https://example.com/img.jpg'],
            'banner' => null,
            'website' => 3.14,
            'nip05' => false,
            'lud16' => 0,
        ], JSON_THROW_ON_ERROR);

        $metadata = ProfileMetadata::fromJsonString($json);

        $this->assertSame('alice', $metadata->getName());
        $this->assertNull($metadata->getDisplayName());
        $this->assertNull($metadata->getAbout());
        $this->assertNull($metadata->getPicture());
        $this->assertNull($metadata->getBanner());
        $this->assertNull($metadata->getWebsite());
        $this->assertNull($metadata->getNip05());
        $this->assertNull($metadata->getLud16());
    }
}
