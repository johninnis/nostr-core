<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Override;
use Stringable;

final readonly class RelayHttpUrl implements Stringable
{
    private string $url;

    public function __construct(RelayUrl $relayUrl)
    {
        $wsUrl = (string) $relayUrl;
        $this->url = $relayUrl->isSecure()
            ? 'https://'.substr($wsUrl, strlen('wss://'))
            : 'http://'.substr($wsUrl, strlen('ws://'));
    }

    #[Override]
    public function __toString(): string
    {
        return $this->url;
    }
}
