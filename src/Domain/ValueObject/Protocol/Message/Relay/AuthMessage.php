<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class AuthMessage extends RelayMessage
{
    public function __construct(private string $challenge)
    {
        if ('' === $this->challenge) {
            throw new InvalidArgumentException('AUTH challenge cannot be empty');
        }
    }

    #[Override]
    public function getType(): string
    {
        return 'AUTH';
    }

    public function getChallenge(): string
    {
        return $this->challenge;
    }

    #[Override]
    public function toArray(): array
    {
        return ['AUTH', $this->challenge];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || 'AUTH' !== $data[0]) {
            return null;
        }

        if (!is_string($data[1]) || '' === $data[1]) {
            return null;
        }

        return new self($data[1]);
    }
}
