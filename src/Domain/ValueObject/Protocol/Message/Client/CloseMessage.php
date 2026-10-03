<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Client;

use Innis\Nostr\Core\Domain\Enum\ClientMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\ClientMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class CloseMessage extends ClientMessage
{
    public function __construct(private SubscriptionId $subscriptionId)
    {
    }

    #[Override]
    public static function type(): ClientMessageType
    {
        return ClientMessageType::Close;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->subscriptionId];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if (1 !== count($payload)) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($payload[0]);

        return null === $subscriptionId ? null : new self($subscriptionId);
    }
}
