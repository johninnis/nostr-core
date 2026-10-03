<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use InvalidArgumentException;

final readonly class ZapAmount
{
    public const int MILLISATS_PER_SAT = 1000;

    public const int MAX_MILLISATS = self::BTC_TO_MILLISATS;

    private const int BTC_TO_MILLISATS = 100_000_000_000;

    private function __construct(private int $millisats)
    {
    }

    public function toMillisats(): int
    {
        return $this->millisats;
    }

    public function toSats(): int
    {
        return intdiv($this->millisats, self::MILLISATS_PER_SAT);
    }

    public function equals(self $other): bool
    {
        return $this->millisats === $other->millisats;
    }

    public static function fromMillisats(int $millisats): self
    {
        return self::tryFromMillisats($millisats) ?? throw new InvalidArgumentException('Amount cannot be negative');
    }

    public static function fromSats(int $sats): self
    {
        if ($sats > intdiv(PHP_INT_MAX, self::MILLISATS_PER_SAT)) {
            throw new InvalidArgumentException(sprintf('Amount of %d sats does not fit in millisats', $sats));
        }

        return self::fromMillisats($sats * self::MILLISATS_PER_SAT);
    }

    public static function tryFromBolt11(string $bolt11): ?self
    {
        // Deliberate: BIP-173 decoders must not accept a mixed-case string, and BOLT-11 parses the invoice as bech32 — see ADR-0083
        if (strtolower($bolt11) !== $bolt11 && strtoupper($bolt11) !== $bolt11) {
            return null;
        }

        $invoice = strtolower($bolt11);
        $separator = strrpos($invoice, '1');

        // Deliberate: only the BOLT-11 bitcoin prefixes; the whole human-readable part, up to the last '1' (the bech32 separator), must be prefix and amount, so an amount-less invoice is rejected, not read as 1 BTC; the amount is a positive integer with no leading zero, as BOLT-11 requires; lowercase because the multipliers are case-sensitive — see ADR-0083
        if (false === $separator || !preg_match('/^ln(?:bcrt|bc|tbs|tb)([1-9]\d*)([munp])?$/D', substr($invoice, 0, $separator), $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $multiplier = $matches[2] ?? '';

        if ('p' === $multiplier) {
            if (!str_ends_with($matches[1], '0')) {
                return null;
            }

            $millisats = intdiv($amount, 10);

            return $millisats > self::MAX_MILLISATS ? null : self::tryFromMillisats($millisats);
        }

        $millisatsPerUnit = match ($multiplier) {
            'm' => intdiv(self::BTC_TO_MILLISATS, 1000),
            'u' => intdiv(self::BTC_TO_MILLISATS, 1_000_000),
            'n' => intdiv(self::BTC_TO_MILLISATS, 1_000_000_000),
            default => self::BTC_TO_MILLISATS,
        };

        if ($amount > intdiv(self::MAX_MILLISATS, $millisatsPerUnit)) {
            return null;
        }

        return self::tryFromMillisats($amount * $millisatsPerUnit);
    }

    private static function tryFromMillisats(int $millisats): ?self
    {
        return $millisats < 0 ? null : new self($millisats);
    }
}
