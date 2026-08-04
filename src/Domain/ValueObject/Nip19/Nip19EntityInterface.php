<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Nip19;

use Innis\Nostr\Core\Domain\Enum\Nip19EntityType;

// Deliberate: an interface, not an abstract base — the variants share no mechanism a base could own, and the analyser-checked exhaustiveness comes from the enum discriminant rather than from inheritance — see ADR-0060
interface Nip19EntityInterface
{
    public function type(): Nip19EntityType;

    public function toBech32(): string;
}
