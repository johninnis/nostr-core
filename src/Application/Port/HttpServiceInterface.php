<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

interface HttpServiceInterface
{
    public function getJson(string $url, array $headers = [], float $timeout = 5.0): ?array;
}
