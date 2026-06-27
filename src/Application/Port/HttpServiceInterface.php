<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

interface HttpServiceInterface
{
    /**
     * Fetches and JSON-decodes a remote document, returning null on any transport or decode failure.
     *
     * The URL is attacker-influenced (relay NIP-11 endpoints, NIP-05 well-known documents), so an
     * implementation MUST, as security obligations the library cannot enforce from here:
     * - bound the response body to a maximum size and abort beyond it (a hostile host can stream
     *   gigabytes);
     * - refuse to follow redirects into private, loopback, or link-local address ranges (SSRF);
     * - treat $timeout as a hard ceiling on the whole request.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>|null
     */
    public function getJson(string $url, array $headers = [], float $timeout = 5.0): ?array;
}
