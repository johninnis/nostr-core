# 34. HTTP transport hardening is the host's obligation behind `HttpServiceInterface`

## Status

Accepted

## Context

`HttpServiceInterface::getJson` fetches and JSON-decodes a remote document for the NIP-05 verifier and
the NIP-11 client. The URL it is given is attacker-influenced: a relay advertises its own NIP-11
endpoint, and a NIP-05 identifier names the host whose `.well-known/nostr.json` is fetched. A naive
implementation of this port is therefore exposed to three transport-level attacks — an unbounded
response body that streams the client out of memory, a redirect into a private, loopback, or
link-local address range (server-side request forgery), and a request that never terminates because no
hard timeout bounds it.

This package defines the port but performs no I/O of its own: it ships no HTTP client and cannot reach
the socket, the redirect handler, or the read loop where those defences must live. The mitigations
belong to whichever client the host injects (cURL, Guzzle, a framework's PSR-18 client), and the host
is the only party that can configure them. Stating that obligation only in the port's source — where
it cannot be enforced and is easily missed — would leave a security-critical division of
responsibility undocumented for the reader who is about to implement the port, which is exactly the
kind of non-obvious, consequential decision that has to survive in the record rather than as a
transient code comment.

## Decision

The library treats response-size bounding, SSRF-safe redirect handling, and a hard request timeout as
the **host's** responsibility, discharged inside the injected `HttpServiceInterface` implementation.
The port carries the `$timeout` argument the host must honour and the typed shapes it returns, and
nothing more; it does not attempt to police the transport it cannot see. An implementer of this port
must bound the response body and abort beyond the limit, refuse redirects into private, loopback, or
link-local ranges, and treat `$timeout` as a hard ceiling on the whole request.

## Consequences

- A consumer that injects an unhardened HTTP client is vulnerable to memory exhaustion, SSRF, and
  hung requests through the NIP-05 and NIP-11 paths. That risk is owned at the composition root, by
  the choice of client, not by this package.
- The port stays a thin contract — a URL, headers, a timeout, and a decoded document or `null` — so it
  remains implementable against any HTTP stack without the library dictating the transport.
- A one-line fence on the interface points here, so the reader implementing the port is sent to the
  full obligation rather than relying on prose carried in the source.
