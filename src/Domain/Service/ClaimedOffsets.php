<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

// Deliberate: a mutable set local to one extraction, counting the offsets it checks so its linear cost is pinned by a test, not a timer — see ADR-0108
final class ClaimedOffsets
{
    /** @var array<int, true> */
    private array $claimed = [];

    private int $offsetsExamined = 0;

    public function claim(int $position, int $length): bool
    {
        $end = $position + $length;

        for ($offset = $position; $offset < $end; ++$offset) {
            ++$this->offsetsExamined;

            if (isset($this->claimed[$offset])) {
                return false;
            }
        }

        for ($offset = $position; $offset < $end; ++$offset) {
            $this->claimed[$offset] = true;
        }

        return true;
    }

    public function offsetsExamined(): int
    {
        return $this->offsetsExamined;
    }
}
