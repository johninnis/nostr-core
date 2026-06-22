<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Timestamp implements Stringable
{
    public function __construct(private int $timestamp)
    {
        if ($this->timestamp < 0) {
            throw new InvalidArgumentException('Timestamp cannot be negative');
        }
    }

    public function toInt(): int
    {
        return $this->timestamp;
    }

    public function toDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->timestamp);
    }

    public function isReasonableAt(self $reference): bool
    {
        $oneHourInFuture = $reference->timestamp + 3600;
        $tenYearsAgo = $reference->timestamp - (10 * 365 * 24 * 3600);

        return $this->timestamp >= $tenYearsAgo && $this->timestamp <= $oneHourInFuture;
    }

    public function isReasonable(): bool
    {
        return $this->isReasonableAt(self::now());
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

    public function hasPassed(): bool
    {
        return !self::now()->isBefore($this);
    }

    public static function now(): self
    {
        return new self(time());
    }

    public static function randomised(int $maxSecondsAgo = 172800): self
    {
        return new self(self::now()->toInt() - random_int(0, $maxSecondsAgo));
    }

    public static function fromInt(int $timestamp): self
    {
        return new self($timestamp);
    }

    public static function tryFromInt(int $timestamp): ?self
    {
        return $timestamp < 0 ? null : new self($timestamp);
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self($dateTime->getTimestamp());
    }

    #[Override]
    public function __toString(): string
    {
        return (string) $this->timestamp;
    }
}
