<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Reference;

use Innis\Nostr\Core\Domain\Enum\RelayMarker;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final readonly class RelayReference
{
    public function __construct(
        private RelayUrl $relayUrl,
        private RelayMarker $marker,
    ) {
    }

    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getMarker(): RelayMarker
    {
        return $this->marker;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'url' => (string) $this->relayUrl,
            'marker' => $this->marker->value,
        ];
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $url = $data['url'] ?? null;
        if (!is_string($url)) {
            return null;
        }

        $relayUrl = RelayUrl::tryFromString($url);
        if (null === $relayUrl) {
            return null;
        }

        return new self(
            $relayUrl,
            RelayMarker::fromTagValue(is_string($data['marker'] ?? null) ? $data['marker'] : null),
        );
    }
}
