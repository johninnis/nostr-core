<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

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

    public static function fromArray(array $data): ?self
    {
        $url = $data['url'] ?? null;
        if (!is_string($url)) {
            return null;
        }

        $relayUrl = RelayUrl::fromString($url);
        if (null === $relayUrl) {
            return null;
        }

        return new self(
            $relayUrl,
            isset($data['mode']) && is_string($data['mode']) ? $data['mode'] : null,
        );
    }
}
