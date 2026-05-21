<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message;

interface PreSerialisedMessage
{
    public function preSerialisedJson(): ?string;
}
