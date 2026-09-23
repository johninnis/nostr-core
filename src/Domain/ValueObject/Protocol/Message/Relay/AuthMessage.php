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
    public function type(): RelayMessageType
    {
        return RelayMessageType::Auth;
    }

    public function getChallenge(): Challenge
    {
        return $this->challenge;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, (string) $this->challenge];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function tryFromArray(array $data): ?static
    {
        if (!array_is_list($data) || 2 !== count($data)) {
            return null;
        }

        $challenge = Challenge::tryFromString($data[1]);

        if (null === $challenge) {
            return null;
        }

        $parsed = new self($challenge);

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
