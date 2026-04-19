<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;

interface ContentReferenceTagBuilderInterface
{
    public function buildTags(EventContent $content, ?TagCollection $existingTags = null): TagCollection;
}
