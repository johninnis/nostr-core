<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class Timestamp
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

    public function isReasonable(): bool
    {
        $now = time();
        $oneHourInFuture = $now + 3600;
        $tenYearsAgo = $now - (10 * 365 * 24 * 3600);

        return $this->timestamp >= $tenYearsAgo && $this->timestamp <= $oneHourInFuture;
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

    public static function now(): self
    {
        return new self(time());
    }

    public static function randomised(int $maxSecondsAgo = 172800): self
    {
        return new self(time() - random_int(0, $maxSecondsAgo));
    }

    public static function fromInt(int $timestamp): self
    {
        return new self($timestamp);
    }

    public static function fromDateTime(DateTimeInterface $dateTime): self
    {
        return new self($dateTime->getTimestamp());
    }

    public function __toString(): string
    {
        return (string) $this->timestamp;
    }
}
