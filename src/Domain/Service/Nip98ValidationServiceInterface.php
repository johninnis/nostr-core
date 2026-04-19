<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface Nip98ValidationServiceInterface
{
    public function validate(Event $event, string $requestUrl, string $requestMethod, ?string $requestBodyHash = null): PublicKey;

    public function validateAuthHeader(string $authHeader, string $requestUrl, string $requestMethod, string $requestBody): PublicKey;
}
