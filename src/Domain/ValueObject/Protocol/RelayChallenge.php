<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class RelayChallenge
{
    public function __construct(
        private RelayUrl $relayUrl,
        private Challenge $challenge,
    ) {
    }

    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getChallenge(): Challenge
    {
        return $this->challenge;
    }
}
