<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class AuthMessage extends RelayMessage
{
    #[Override]
    public function type(): RelayMessageType
    {
        return RelayMessageType::Auth;
    }

    public function __construct(private string $challenge)
    {
        if ('' === $this->challenge) {
            throw new InvalidArgumentException('AUTH challenge cannot be empty');
        }
    }

    public function getChallenge(): string
    {
        return $this->challenge;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, $this->challenge];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || RelayMessageType::Auth->value !== $data[0]) {
            return null;
        }

        if (!is_string($data[1]) || '' === $data[1]) {
            return null;
        }

        return new self($data[1]);
    }
}
