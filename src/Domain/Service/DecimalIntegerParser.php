<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class DecimalIntegerParser
{
    private function __construct()
    {
    }

    /**
     * @return non-negative-int|null
     */
    // Deliberate: only canonical digits are read; a sign, a leading zero, a fraction, an exponent or whitespace is no decimal, so one value has one spelling — see nostr-adrs ADR-0096
    public static function tryParse(string $value): ?int
    {
        if (!ctype_digit($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return false === $integer ? null : $integer;
    }
}
