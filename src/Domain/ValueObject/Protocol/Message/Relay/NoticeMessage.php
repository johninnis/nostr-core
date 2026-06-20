<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class NoticeMessage extends RelayMessage
{
    public function __construct(private string $message)
    {
        if ('' === $this->message) {
            throw new InvalidArgumentException('Notice message cannot be empty');
        }
    }

    #[Override]
    public function getType(): string
    {
        return 'NOTICE';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    #[Override]
    public function toArray(): array
    {
        return ['NOTICE', $this->message];
    }

    #[Override]
    public static function fromArray(array $data): ?static
    {
        if (2 !== count($data) || 'NOTICE' !== $data[0]) {
            return null;
        }

        if (!is_string($data[1]) || '' === $data[1]) {
            return null;
        }

        return new self($data[1]);
    }
}
