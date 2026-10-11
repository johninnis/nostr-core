<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Closure;
use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;

final readonly class SoleTagValue
{
    /**
     * @param list<string> $distinctValues
     */
    private function __construct(private array $distinctValues)
    {
    }

    /**
     * @param list<string>                    $values
     * @param (Closure(string): ?string)|null $canonicalise
     */
    public static function fromValues(array $values, ?Closure $canonicalise = null): self
    {
        $canonical = null === $canonicalise
            ? $values
            : array_filter(array_map($canonicalise, $values), static fn (?string $value): bool => null !== $value);

        return new self(array_values(array_unique($canonical, SORT_STRING)));
    }

    public function getState(): SoleTagValueState
    {
        return match (count($this->distinctValues)) {
            0 => SoleTagValueState::Absent,
            1 => SoleTagValueState::One,
            default => SoleTagValueState::Disagreeing,
        };
    }

    public function getValue(): ?string
    {
        return 1 === count($this->distinctValues) ? $this->distinctValues[0] : null;
    }

    /**
     * @return list<string>
     */
    public function getDistinctValues(): array
    {
        return $this->distinctValues;
    }
}
