<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

interface HttpServiceInterface
{
    /**
     * Fetches a JSON document, returning null when the request fails or the body is not a JSON object.
     *
     * This port is called with a URL derived from untrusted input — a NIP-05 identifier's domain or a
     * relay's NIP-11 address — so the implementation, not this package, owns the request hardening. An
     * adapter is required to:
     *
     * - Refuse hosts that resolve into private, loopback, link-local or cloud-metadata ranges
     *   (RFC1918, 127.0.0.0/8, ::1, 169.254.0.0/16, fd00::/8), resolving the name itself rather than
     *   pattern-matching the string.
     * - Re-apply that check to **every redirect target**, not only the first URL, and bound the
     *   redirect count. A permitted host redirecting to an internal one is the usual SSRF route.
     * - Cap the response body it will buffer, so a hostile peer cannot stream until memory is gone.
     *   Nothing downstream limits this.
     * - Treat `$timeout` as a hard ceiling on the whole call, not a per-socket-operation timeout.
     *
     * None of these are enforced here — this package cannot see the adapter's transport. An adapter
     * that skips them exposes its host to SSRF and to memory exhaustion from a hostile relay.
     *
     * @param array<string, string> $headers
     *
     * @return array<string, mixed>|null
     */
    // Deliberate: the obligations above sit on the port, where an implementer reads them, rather than only in SECURITY.md — see ADR-0034
    public function getJson(string $url, array $headers = [], float $timeout = 5.0): ?array;
}
