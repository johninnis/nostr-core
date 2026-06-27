<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

interface HttpServiceInterface
{
    // Deliberate: the implementer owns response-size, SSRF-redirect, and timeout hardening — see ADR-0034
    /**
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>|null
     */
    public function getJson(string $url, array $headers = [], float $timeout = 5.0): ?array;
}
