# 136. `RumourFactory::createHttpAuth` writes the method as Fetch sends it, and `Nip98Request` keeps the method as it arrived

## Status

Accepted

## Context

A NIP-98 auth event binds itself to one HTTP request through its `u`, `method` and `payload` tags, and NIP-98 says the `method` tag "MUST be the same HTTP method used for the requested resource". RFC 9110: "The method token is case-sensitive". nostr-adrs ADR-0070 decides for every implementation that the `method` tag is compared with the request's method byte for byte, with case, and that a builder writes the method as the request will be sent.

A client does not always send the method it was given. The Fetch standard upper-cases a method that is a byte-case-insensitive match for `DELETE`, `GET`, `HEAD`, `OPTIONS`, `POST` or `PUT`, and sends any other method as written. A PHP client built on this package may sign a request a browser or a TypeScript client then sends, and the same request must build the same event in either library. `createHttpAuth` wrote the method exactly as `Nip98Request` held it, so an event built for `post` named `post`, and the request Fetch sent as `POST` was refused.

`Nip98Request` is the request on both sides of the exchange: a client builds the event from it, and `Nip98EventChecker::validateMethod` compares the `method` tag against it on the server (ADR-0110). Upper-casing in `Nip98Request` would put the normalisation on the server's side as well, comparing against a method the client never sent: an event signed for `patch`, sent as `patch`, would be refused once the server's copy said `PATCH`.

## Decision

- `RumourFactory::createHttpAuth` writes the `method` tag as Fetch sends the method: one of Fetch's six normalised methods (`DELETE`, `GET`, `HEAD`, `OPTIONS`, `POST`, `PUT`), given in any case, is written upper-cased; any other method is written exactly as given. The match is over ASCII letters, as Fetch's byte-case-insensitive match is, so a method spelled with any other character is written as given.
- Nothing is refused: a lower-case standard method is upper-cased, not rejected.
- `Nip98Request` keeps the method exactly as it was given. The upper-casing lives in the builder alone, and `Nip98EventChecker` compares the tag against the method as it arrived on the wire, byte for byte.

## Consequences

- A builder given `post` writes `POST`, matching what Fetch sends, and the same request builds the same event here and in @innis/nostr-core's `buildNip98AuthEvent`.
- `patch` and `PATCH` stay distinct: Fetch sends `patch` as written, and the builder writes it so.
- Do not move the upper-casing into `Nip98Request`, and do not upper-case every method: the first changes what a server compares against, the second breaks every method Fetch does not normalise.
- Shared decision: nostr-adrs ADR-0070.
