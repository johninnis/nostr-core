<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;

final readonly class SoleTagValue
{
    private function __construct(private SoleTagValueState $state, private ?string $value)
    {
    }

    /**
     * @param list<string> $values
     */
    public static function fromValues(array $values): self
    {
        $distinct = array_values(array_unique($values, SORT_STRING));

        return match (count($distinct)) {
            0 => new self(SoleTagValueState::Absent, null),
            1 => new self(SoleTagValueState::One, $distinct[0]),
            default => new self(SoleTagValueState::Disagreeing, null),
        };
    }

    public function getState(): SoleTagValueState
    {
        return $this->state;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
