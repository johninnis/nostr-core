<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Support;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final class RelayUrlMother
{
    private const int RELAYS_BEYOND_THE_NIP19_BOUND = 20;
    private const int LONG_RELAY_PATH_LENGTH = 160;

    public static function beyondTheNip19EncodingBound(): RelayUrlCollection
    {
        return new RelayUrlCollection(array_map(
            static fn (int $index): RelayUrl => RelayUrl::fromString(sprintf('wss://relay%d.example.com/%s', $index, str_repeat('a', self::LONG_RELAY_PATH_LENGTH))),
            range(1, self::RELAYS_BEYOND_THE_NIP19_BOUND),
        ));
    }
}
