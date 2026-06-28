<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Application\DTO\Nip98Request;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Nip98ValidatorInterface
{
    public function validate(Event $event, Nip98Request $request): PublicKey|Nip98ValidationFailure;

    public function validateAuthHeader(string $authHeader, Nip98Request $request): PublicKey|Nip98ValidationFailure;
}
