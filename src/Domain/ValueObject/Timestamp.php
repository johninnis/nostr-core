<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Timestamp implements Stringable
{
    private function __construct(private int $timestamp)
    {
    }

    public function toInt(): int
    {
        return $this->timestamp;
    }

    public function toDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->timestamp);
    }

    public function equals(self $other): bool
    {
        return $this->timestamp === $other->timestamp;
    }

    public function isAfter(self $other): bool
    {
        return $this->timestamp > $other->timestamp;
    }

    public function isBefore(self $other): bool
    {
        return $this->timestamp < $other->timestamp;
    }

    public function compareTo(self $other): int
    {
        return $this->timestamp <=> $other->timestamp;
    }

    public function differenceInSeconds(self $other): int
    {
        return abs($this->timestamp - $other->timestamp);
    }

    public function isWithinSecondsOf(self $reference, int $toleranceSeconds): bool
    {
        return $this->differenceInSeconds($reference) <= $toleranceSeconds;
    }

    public function hasPassedAt(self $reference): bool
    {
        return !$reference->isBefore($this);
    }

    // Deliberate: reads time() directly rather than through an injected clock; no elapsed-time behaviour under test here — see ADR-0005
    public static function now(): self
    {
        return self::fromInt(time());
    }

    // Deliberate: reads the entropy source directly, not via an injected port; no random-dependent output under test — see ADR-0018
    public static function randomised(int $maxSecondsAgo = 172800): self
    {
        return self::fromInt(self::now()->toInt() - random_int(0, $maxSecondsAgo));
    }

    public static function fromInt(int $timestamp): self
    {
        return self::tryFromInt($timestamp) ?? throw new InvalidArgumentException('Timestamp cannot be negative');
    }

    public static function tryFromInt(int $timestamp): ?self
    {
        return $timestamp < 0 ? null : new self($timestamp);
    }

    public static function tryFromDecimalString(string $value): ?self
    {
        $seconds = DecimalIntegerParser::tryParse($value);

        return null === $seconds ? null : self::tryFromInt($seconds);
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return self::fromInt($dateTime->getTimestamp());
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->timestamp;
    }
}
