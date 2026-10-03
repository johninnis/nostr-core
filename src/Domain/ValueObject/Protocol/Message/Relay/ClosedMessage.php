<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class ClosedMessage extends RelayMessage
{
    private function __construct(
        private SubscriptionId $subscriptionId,
        private string $message,
    ) {
    }

    public static function closed(SubscriptionId $subscriptionId, ReasonPrefix $prefix, string $detail): self
    {
        return new self($subscriptionId, $prefix->format($detail));
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Closed;
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    // Deliberate: parsed from the message on demand rather than stored beside it, so the prefix can never disagree with the text the peer sent — see ADR-0087
    public function getReasonPrefix(): ReasonPrefix
    {
        return ReasonPrefix::ofRefusal($this->message);
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->subscriptionId, $this->message];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if (count($payload) < 2) {
            return null;
        }

        [$subscriptionIdValue, $message] = $payload;
        $subscriptionId = SubscriptionId::tryFromString($subscriptionIdValue);

        return null === $subscriptionId || !is_string($message) ? null : new self($subscriptionId, $message);
    }
}
