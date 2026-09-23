# 70. An AUTH challenge is a value object that cannot be empty

## Status

Accepted

## Context

NIP-42 authentication rests on one secret: the relay sends a challenge, and a client proves it holds the key by signing that exact string back. Everything else in the exchange is public. The challenge is therefore the only value in the flow whose handling has security consequences, and it was the one value in the flow modelled as a bare `string`.

That left the invariant stated in one place and needed in three. `AuthMessage` refused an empty challenge in its constructor, because a relay that sends `["AUTH", ""]` has issued no challenge at all. The validator comparing a client's answer had no such guard, and PHP's `hash_equals('', '')` is `true`, so a host whose challenge lookup returned an empty string for an unknown, expired or already-consumed connection would have authenticated any correctly-shaped event. A relay generating challenges had the same freedom to produce one by accident. Three places, one rule, enforced in one of them.

Comparison had the same shape of problem. A challenge is a secret being checked against an attacker-supplied value, so the comparison should not leak where the two diverge; whether it does was a property of each call site rather than of the value.

## Decision

`Challenge` is a protocol value object beside `SubscriptionId` and `RelayUrl`. It is built through the trust boundary the package uses everywhere: `Challenge::tryFromString()` returns null for anything that is not a non-empty string, `Challenge::fromString()` asserts the same on a trusted value and throws otherwise. `equals()` compares in constant time with `hash_equals`.

`AuthMessage` carries a `Challenge` and parses one from the wire; `Nip42Validator::validate()` takes one; `RumourFactory::createAuth()` takes one; `TagReferences` holds a `ChallengeCollection`, so the general tag-reference extractor parses a `challenge` tag rather than handing back its raw text. There is no path through this library on which a challenge is an unvalidated string.

## Consequences

- An empty challenge cannot exist, so no relay built on this library can issue one and no validator can accept one. The invariant is established once, at construction, and never re-checked.
- Timing-safe comparison belongs to the value, not to its callers, so a new call site gets it without knowing to ask.
- Do not add a `getValue(): string` accessor beside `__toString()`, and do not compare challenges with `===`. Both put the comparison back in the caller's hands, which is what this record removes.
- The challenge stays an arbitrary non-empty string. NIP-42 places no further constraint on it, so this library places none: a relay may use whatever it likes as long as it is not nothing.
