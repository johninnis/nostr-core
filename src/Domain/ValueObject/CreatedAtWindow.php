<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use InvalidArgumentException;

final readonly class CreatedAtWindow
{
    public const int DEFAULT_SECONDS_BEHIND = 315_360_000;
    public const int DEFAULT_SECONDS_AHEAD = 3600;

    public function __construct(
        private int $secondsBehind = self::DEFAULT_SECONDS_BEHIND,
        private int $secondsAhead = self::DEFAULT_SECONDS_AHEAD,
    ) {
        if ($secondsBehind < 0 || $secondsAhead < 0) {
            throw new InvalidArgumentException(sprintf('A created_at window cannot be negative, got %d seconds behind and %d ahead', $secondsBehind, $secondsAhead));
        }
    }

    public function admits(Timestamp $createdAt, Timestamp $reference): bool
    {
        $offset = $createdAt->toInt() - $reference->toInt();

        return $offset >= -$this->secondsBehind && $offset <= $this->secondsAhead;
    }
}
