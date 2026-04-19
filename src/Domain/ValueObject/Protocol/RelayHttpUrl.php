<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class RelayHttpUrl
{
    private string $url;

    public function __construct(RelayUrl $relayUrl)
    {
        $wsUrl = (string) $relayUrl;
        $this->url = $relayUrl->isSecure()
            ? 'https://'.substr($wsUrl, 6)
            : 'http://'.substr($wsUrl, 5);
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
