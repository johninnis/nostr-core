<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Application\Port;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;

interface HttpServiceInterface
{
    public const string USER_AGENT = 'innis/nostr-core';

    /**
     * @param array<string, string> $headers
     */
    // Deliberate: returns the raw body or HttpFetchFailure (NotFound for a 404, NoAnswer for anything else) so the library reads every body in one place; the implementer never follows a redirect, refuses a private, loopback or link-local destination after DNS resolution, bounds the body and treats the timeout as a hard ceiling — see ADR-0090
    public function get(HttpUrl $url, array $headers = [], float $timeout = 10.0): string|HttpFetchFailure;
}
