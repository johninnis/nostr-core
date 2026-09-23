# 73. The `Nostr` auth-scheme token is matched without regard to case

## Status

Accepted

## Context

NIP-98 puts a signed event in an HTTP `Authorization` header behind the scheme token `Nostr`. This package decoded that header by requiring the exact bytes `Nostr ` at the front.

Loosening a check on an authentication header is the kind of change that should be argued rather than assumed, which is why this record exists. The argument is that the strict version was not enforcing anything.

RFC 9110 defines the auth-scheme token as case-insensitive. A client sending `nostr ` or `NOSTR ` is sending a well-formed header that names this scheme, and every general-purpose HTTP stack in the chain is entitled to treat it as equivalent. Rejecting it is not a security control, because the scheme token carries no secret and proves nothing: it says which format follows, and the format that follows is a signed event whose signature, timestamp, URL, method and payload hash are all checked afterwards. An attacker gains nothing from writing the token in lower case, and a legitimate client loses a working request.

The comparison is also not the place where anything is decided. If the token were somehow wrong, the base64 payload behind it would still have to decode to an event this relay's own challenge and validation accept.

Against that, the strict form refuses a header the specification says is valid, and refuses it as a format error rather than an authentication error, so the refusal does not point at the cause. Anything in the chain that normalises the case of a header value, which RFC 9110 permits for this token, produces that outcome.

## Decision

The scheme token is matched case-insensitively, with a length-bounded comparison against the known prefix. Everything after the token is unchanged: the payload is still base64-decoded, still parsed as an event, and still validated in full.

The comparison is not constant-time, deliberately. The scheme token is a public constant of the protocol, not a secret, so there is nothing for a timing difference to leak.

## Consequences

- A client that writes the scheme token in any case reaches the validator, which is the thing that actually decides whether the request is authentic.
- Nothing about what the package accepts as an authenticated request changes. The set of headers that authenticate is identical; only the set that are recognised as attempts widened.
- Do not "harden" this back to an exact-bytes comparison. It refuses well-formed requests that RFC 9110 says are equivalent, and it protects nothing, because the token is public and the signature is checked regardless.
- Do not extend the same reasoning to the rest of the header. Everything after the token is data, and the base64 payload is matched exactly.
