<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

final readonly class ZapAmount
{
    public const MILLISATS_PER_SAT = 1000;

    private const BTC_TO_MILLISATS = 100_000_000_000;

    private function __construct(private int $millisats)
    {
        if ($this->millisats < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
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
        if (!preg_match('/^ln[a-z]+?(\d+)([munp])?/i', $bolt11, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $multiplier = $matches[2] ?? '';

        $millisats = match ($multiplier) {
            'm' => $amount * intdiv(self::BTC_TO_MILLISATS, 1000),
            'u' => $amount * intdiv(self::BTC_TO_MILLISATS, 1_000_000),
            'n' => $amount * intdiv(self::BTC_TO_MILLISATS, 1_000_000_000),
            'p' => intdiv($amount * self::BTC_TO_MILLISATS, 1_000_000_000_000),
            default => $amount * self::BTC_TO_MILLISATS,
        };

        return new self($millisats);
    }
}
