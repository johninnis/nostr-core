<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;

interface ContentReferenceTagBuilderInterface
{
    public function buildTags(EventContent $content, ?TagCollection $existingTags = null): TagCollection;
}
