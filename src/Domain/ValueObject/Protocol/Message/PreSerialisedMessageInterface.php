<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

interface PreSerialisedMessageInterface
{
    public function preSerialisedJson(): ?string;
}
