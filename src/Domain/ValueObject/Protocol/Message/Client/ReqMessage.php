<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

final readonly class ReqMessage extends FilterRequestMessage
{
    protected const string TYPE = 'REQ';
}
