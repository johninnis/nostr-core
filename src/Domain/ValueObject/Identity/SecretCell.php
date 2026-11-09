<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use SensitiveParameter;

// Deliberate: mutable so sodium_memzero can wipe the bytes in place, which a string held directly by SensitiveParameterValue would not allow — see ADR-0148
final class SecretCell
{
    public function __construct(#[SensitiveParameter] public ?string $bytes)
    {
    }
}
