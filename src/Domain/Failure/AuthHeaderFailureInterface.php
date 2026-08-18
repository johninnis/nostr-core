<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Failure;

interface AuthHeaderFailureInterface
{
    public function message(): string;
}
