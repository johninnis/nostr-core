<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class AuthMessage extends RelayMessage
{
    protected const string TYPE = 'AUTH';

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

    #[Override]
    public function toArray(): array
    {
        return [self::TYPE, $this->challenge];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || self::TYPE !== $data[0]) {
            return null;
        }

        if (!is_string($data[1]) || '' === $data[1]) {
            return null;
        }

        return new self($data[1]);
    }
}
