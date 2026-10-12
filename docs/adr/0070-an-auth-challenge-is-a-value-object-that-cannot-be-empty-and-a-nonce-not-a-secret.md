# 70. An AUTH challenge is a value object that cannot be empty, and is a nonce, not a secret

## Status

Accepted

## Context

NIP-42 authentication rests on one per-connection value: the relay sends a challenge, and a client proves it holds the key by signing that exact string back. The challenge is a nonce: it is sent in the clear, and a signed answer proves something only because the challenge was fresh and belongs to that connection. Its handling still decides whether an answer proves anything, and it was once the one value in the flow modelled as a bare `string`.

That left the invariant stated in one place and needed in three. `AuthMessage` refused an empty challenge in its constructor, because a relay that sends `["AUTH", ""]` has issued no challenge at all. The validator comparing a client's answer had no such guard, and PHP's `hash_equals('', '')` is `true`, so a host whose challenge lookup returned an empty string for an unknown, expired or already-consumed connection would have authenticated any correctly-shaped event. A relay generating challenges had the same freedom to produce one by accident. Three places, one rule, enforced in one of them.

Comparison had the same shape of problem: whether a comparison leaks where the two strings diverge was a property of each call site rather than of the value.

## Decision

`Challenge` is a protocol value object beside `SubscriptionId` and `RelayUrl`. It is built through the trust boundary the package uses everywhere: `Challenge::tryFromString()` returns null for anything that is not a non-empty string, `Challenge::fromString()` asserts the same on a trusted value and throws otherwise. `equals()` compares in constant time with `hash_equals`.

`AuthMessage` carries a `Challenge` and parses one from the wire; `RelayChallenge` pairs it with the relay that issued it, and `Nip42Validator::validate()` and `RumourFactory::createAuth()` take that pair (ADR-0143); `TagReferences` holds a `ChallengeCollection`, so the general tag-reference extractor parses a `challenge` tag rather than handing back its raw text. There is no path through this library on which a challenge is an unvalidated string.

The constant-time comparison is defence in depth, not the protection: the challenge is sent in the clear, and it costs nothing to keep it from ever being compared by a path that leaks timing.

## Consequences

- An empty challenge cannot exist, so no relay built on this library can issue one and no validator can accept one. The invariant is established once, at construction, and never re-checked.
- Timing-safe comparison belongs to the value, not to its callers, so a new call site gets it without knowing to ask.
- A relay keeps its challenge fresh and bound to its connection; that, not secrecy, is what makes an answer to it mean anything. Do not treat the challenge as a secret to protect.
- Do not add a `getValue(): string` accessor beside `__toString()`, and do not compare challenges with `===`. Both put the comparison back in the caller's hands, which is what this record removes.
- The challenge stays an arbitrary non-empty string. NIP-42 places no further constraint on it, so this library places none: a relay may use whatever it likes as long as it is not nothing.
- Shared decision: nostr-adrs ADR-0025.
