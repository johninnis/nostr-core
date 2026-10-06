<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final class ExpirationDerivation
{
    private function __construct()
    {
    }

    // Deliberate: the one derivation of an event's expiry from the values it states — the earliest that parses as a canonical decimal timestamp wins, the rest are ignored, so tag order never decides — see ADR-0071 and shared ADR-0011
    /**
     * @param iterable<string> $statedExpiries
     */
    public static function earliestStated(iterable $statedExpiries): ?Timestamp
    {
        $earliest = null;

        foreach ($statedExpiries as $value) {
            $expiry = Timestamp::tryFromDecimalString($value);

            if (null !== $expiry && (null === $earliest || $expiry->isBefore($earliest))) {
                $earliest = $expiry;
            }
        }

        return $earliest;
    }
}
