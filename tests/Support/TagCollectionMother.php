<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use RuntimeException;

final class TagCollectionMother
{
    public static function fromRaw(array $rawTags): TagCollection
    {
        return TagCollection::fromArray($rawTags) ?? throw new RuntimeException('Invalid test tag data');
    }
}
