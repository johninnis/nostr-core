<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use RuntimeException;

final class TagCollectionMother
{
    /**
     * @param list<list<string|false>> $rawTags
     */
    public static function fromRaw(array $rawTags): TagCollection
    {
        return TagCollection::fromWire($rawTags) ?? throw new RuntimeException('Invalid test tag data');
    }
}
