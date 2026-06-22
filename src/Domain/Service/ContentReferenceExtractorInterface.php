<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Reference\ContentReferenceCollection;

interface ContentReferenceExtractorInterface
{
    public function extractContentReferences(EventContent $content): ContentReferenceCollection;
}
