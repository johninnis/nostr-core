<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject;

use InvalidArgumentException;

final readonly class EventLimits
{
    public const int DEFAULT_MAX_CONTENT_LENGTH = 65536;
    public const int DEFAULT_MAX_TAG_COUNT = 5000;

    public function __construct(
        private int $maxContentLength = self::DEFAULT_MAX_CONTENT_LENGTH,
        private int $maxTagCount = self::DEFAULT_MAX_TAG_COUNT,
        private CreatedAtWindow $createdAtWindow = new CreatedAtWindow(),
    ) {
        if ($maxContentLength < 1 || $maxTagCount < 1) {
            throw new InvalidArgumentException(sprintf('Event limits must be positive, got a content length of %d and a tag count of %d', $maxContentLength, $maxTagCount));
        }
    }

    public function admitsContentLength(int $characters): bool
    {
        return $characters <= $this->maxContentLength;
    }

    public function admitsTagCount(int $tags): bool
    {
        return $tags <= $this->maxTagCount;
    }

    public function admitsCreatedAt(Timestamp $createdAt, Timestamp $reference): bool
    {
        return $this->createdAtWindow->admits($createdAt, $reference);
    }
}
