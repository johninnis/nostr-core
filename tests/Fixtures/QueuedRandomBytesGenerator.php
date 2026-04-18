<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Fixtures;

use Innis\Nostr\Core\Application\Port\RandomBytesGeneratorInterface;
use RuntimeException;

final class QueuedRandomBytesGenerator implements RandomBytesGeneratorInterface
{
    /**
     * @param list<string> $queue each entry consumed in order; the next bytes() call returns the next entry
     */
    public function __construct(private array $queue)
    {
    }

    public static function withBytes(string ...$entries): self
    {
        return new self(array_values($entries));
    }

    public function bytes(int $length): string
    {
        if ([] === $this->queue) {
            throw new RuntimeException(sprintf('QueuedRandomBytesGenerator exhausted on bytes(%d) call', $length));
        }

        $next = array_shift($this->queue);

        if (strlen($next) !== $length) {
            throw new RuntimeException(sprintf('QueuedRandomBytesGenerator: expected %d bytes for next call, queued entry has %d', $length, strlen($next)));
        }

        return $next;
    }
}
