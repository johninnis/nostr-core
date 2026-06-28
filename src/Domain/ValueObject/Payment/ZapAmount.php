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
        if ($this->millisats < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
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
        return new self($millisats);
    }

    public static function fromSats(int $sats): self
    {
        return new self($sats * self::MILLISATS_PER_SAT);
    }

    public static function fromBolt11(string $bolt11): ?self
    {
        // Deliberate: the trailing '1' anchors the amount to the bech32 separator so an amount-less invoice is rejected, not read as 1 BTC; lowercase because the multipliers are case-sensitive — see ADR-0040
        if (!preg_match('/^ln[a-z]+?(\d+)([munp])?1/', strtolower($bolt11), $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $multiplier = $matches[2] ?? '';

        if ('p' === $multiplier) {
            $millisats = intdiv($amount, 10);

            return $millisats > self::MAX_MILLISATS ? null : new self($millisats);
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

        return new self($amount * $millisatsPerUnit);
    }
}
