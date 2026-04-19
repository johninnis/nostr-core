<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Exception\InvalidReferenceException;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final readonly class RelayReference
{
    public function __construct(
        private RelayUrl $relayUrl,
        private ?string $mode = null,
    ) {
    }

    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function toArray(): array
    {
        return [
            'url' => (string) $this->relayUrl,
            'mode' => $this->mode,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            RelayUrl::fromString($data['url']) ?? throw new InvalidReferenceException('Corrupt URL in serialised RelayReference'),
            $data['mode'] ?? null
        );
    }
}
