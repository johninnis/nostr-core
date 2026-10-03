<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Override;

final readonly class AuthMessage extends RelayMessage
{
    public function __construct(private Challenge $challenge)
    {
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Auth;
    }

    public function getChallenge(): Challenge
    {
        return $this->challenge;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->challenge];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if ([] === $payload) {
            return null;
        }

        $challenge = Challenge::tryFromString($payload[0]);

        return null === $challenge ? null : new self($challenge);
    }
}
