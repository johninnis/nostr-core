<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Override;

final readonly class ReqMessage extends FilterRequestMessage
{
    #[Override]
    public function type(): ClientMessageType
    {
        return ClientMessageType::Req;
    }
}
