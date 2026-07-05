<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
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
    public function type(): RelayMessageType
    {
        return RelayMessageType::Notice;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return list<mixed>
     */
    #[Override]
    public function toArray(): array
    {
        return [$this->type()->value, $this->message];
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

        if (!is_string($data[1]) || '' === $data[1]) {
            return null;
        }

        $parsed = new self($data[1]);

        return $parsed->type()->value === $data[0] ? $parsed : null;
    }
}
