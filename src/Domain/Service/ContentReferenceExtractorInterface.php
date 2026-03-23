<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;

interface ContentReferenceExtractorInterface
{
    public function extractContentReferences(EventContent $content): array;
}
