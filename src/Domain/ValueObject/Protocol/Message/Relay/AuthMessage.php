<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;

final readonly class AuthMessage extends RelayMessage
{
    public function __construct(private string $challenge)
    {
        if ('' === $this->challenge) {
            throw new InvalidArgumentException('AUTH challenge cannot be empty');
        }
    }

    public function getType(): string
    {
        return 'AUTH';
    }

    public function getChallenge(): string
    {
        return $this->challenge;
    }

    public function toArray(): array
    {
        return ['AUTH', $this->challenge];
    }

    public static function fromArray(array $data): static
    {
        if (2 !== count($data) || 'AUTH' !== $data[0]) {
            throw new InvalidArgumentException('Invalid AUTH message format');
        }

        if (!is_string($data[1])) {
            throw new InvalidArgumentException('AUTH challenge must be a string');
        }

        return new self($data[1]);
    }
}
