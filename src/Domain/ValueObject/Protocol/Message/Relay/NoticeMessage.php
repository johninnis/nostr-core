<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use InvalidArgumentException;
use Override;

final readonly class NoticeMessage extends RelayMessage
{
    protected const string TYPE = 'NOTICE';

    public function __construct(private string $message)
    {
        if ('' === $this->message) {
            throw new InvalidArgumentException('Notice message cannot be empty');
        }
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
        return [self::TYPE, $this->message];
    }

    /**
     * @param array<array-key, mixed> $data
     */
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
