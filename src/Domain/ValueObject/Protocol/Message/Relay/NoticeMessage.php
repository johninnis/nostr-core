<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;

final readonly class NoticeMessage extends RelayMessage
{
    public function __construct(private string $message)
    {
        if ('' === $this->message) {
            throw new InvalidArgumentException('Notice message cannot be empty');
        }
    }

    public function getType(): string
    {
        return 'NOTICE';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return ['NOTICE', $this->message];
    }

    public static function fromArray(array $data): static
    {
        if (2 !== count($data) || 'NOTICE' !== $data[0]) {
            throw new InvalidArgumentException('Invalid NOTICE message format');
        }

        if (!is_string($data[1])) {
            throw new InvalidArgumentException('NOTICE message payload must be a string');
        }

        return new self($data[1]);
    }
}
