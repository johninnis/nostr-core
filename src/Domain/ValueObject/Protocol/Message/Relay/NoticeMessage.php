<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;

final readonly class NoticeMessage extends RelayMessage
{
    public function __construct(private string $message)
    {
        if ($this->message === '') {
            throw new \InvalidArgumentException('Notice message cannot be empty');
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
        if (\count($data) !== 2 || $data[0] !== 'NOTICE') {
            throw new \InvalidArgumentException('Invalid NOTICE message format');
        }

        return new self($data[1]);
    }
}
