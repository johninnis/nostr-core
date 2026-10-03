<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class NoticeMessage extends RelayMessage
{
    private function __construct(private string $message)
    {
    }

    public static function tryFromString(mixed $message): ?self
    {
        return is_string($message) && '' !== $message ? new self($message) : null;
    }

    public static function fromString(string $message): self
    {
        return self::tryFromString($message) ?? throw new InvalidArgumentException('Notice message cannot be empty');
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Notice;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    #[Override]
    protected function toPayload(): array
    {
        return [$this->message];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        return [] === $payload ? null : self::tryFromString($payload[0]);
    }
}
