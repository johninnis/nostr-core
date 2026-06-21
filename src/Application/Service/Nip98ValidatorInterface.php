<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\Nip98ValidationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Nip98ValidatorInterface
{
    public function validate(Event $event, string $requestUrl, string $requestMethod, ?string $requestBodyHash = null): PublicKey|Nip98ValidationFailure;

    public function validateAuthHeader(string $authHeader, string $requestUrl, string $requestMethod, string $requestBody): PublicKey|Nip98ValidationFailure;
}
