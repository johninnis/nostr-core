# 16. The protocol message hierarchy uses inheritance for a discriminated union

## Status

Accepted

## Context

The relay protocol is a closed set of message types in two families — client-to-relay (`EVENT`, `REQ`, `CLOSE`, `AUTH`, `COUNT`) and relay-to-client (`EVENT`, `OK`, `EOSE`, `CLOSED`, `NOTICE`, `AUTH`, `COUNT`). The codebase models them with inheritance:

- An abstract `Message` base owns the one shared mechanism — `encode()`, the wire serialisation — and declares the contract every message satisfies (`getType`, `toArray`, `fromArray`, `fromJson`).
- Abstract `ClientMessage` and `RelayMessage` extend it. Each is the discriminator for its family and owns that family's `toJson()` strategy: a client message always encodes its array; a relay message returns its pre-serialised JSON when it carries one and otherwise encodes.
- The concrete leaves (`Client\ReqMessage`, `Relay\OkMessage`, …) are `final`, each carrying that message's own data and `toArray`/`fromArray` logic.
- One step deeper, `Client\FilterRequestMessage` is an abstract base owning the complete REQ/COUNT mechanism (validation, `getType`, `toArray`, `fromArray`); `ReqMessage` and `CountMessage` are `final` leaves that supply only `protected const string TYPE` (`'REQ'` / `'COUNT'`) and no behaviour.

Two things here invite a well-meaning "simplification" and so are recorded. First, a base shared by leaves that each carry different data and logic is a discriminated union, which can look like inheritance reached for as code reuse. Second, `ReqMessage` and `CountMessage` differ only by a constant, which can look like needless subclassing that an enum or a single parameterised class would replace.

## Decision

Keep the inheritance. It models a closed sum type whose variants must stay distinct types at call sites.

- The leaves are **distinct types used at boundaries.** `MessageDeserialiserInterface` returns `?ClientMessage` / `?RelayMessage` and dispatches on the wire tag to a concrete leaf; callers branch on the concrete type to read its fields. Collapsing a family onto one class carrying a `type` string would erase the per-variant type and turn that branching into runtime field-presence checks.
- For `FilterRequestMessage`, the base is the complete shared mechanism and the leaves differ only by the wire tag — but REQ and COUNT must still be separate types a caller can name and match on. A single class with `req()` / `count()` named constructors would not give them separate types, and keeping separate classes that each forward to a shared implementation would only add wrapper code around the base. The abstract base with constant-only leaves is the smallest design that keeps the two as distinct, self-validating types.
- `toJson()` lives on `ClientMessage` and `RelayMessage`, not on `Message`, because the two families serialise differently (a relay message may already hold pre-serialised JSON). It is one method per family, not one method duplicated.
- `fromJson` and the deserialiser are two different operations sharing one decode step. `Message::fromJson` — defined once on the base, inherited by every leaf — parses a string as a *known* message type (`OkMessage::fromJson($json)` returns `?OkMessage`). `MessageDeserialiserInterface` parses a string of *unknown* type, resolving the family and wire tag to the right leaf. The shared decode-then-`fromArray` step lives once on the base so neither path re-rolls it.

## Consequences

- Base classes are abstract; leaves are `final`. Adding a message is adding a leaf (and, for inbound routing, a deserialiser arm).
- Do not collapse a family onto a single class with a `type` field, and do not turn `ReqMessage` / `CountMessage` into an enum or one parameterised class — each removes a distinct type that callers match on.
- Do not hoist `toJson()` onto `Message`; the two families serialise differently.
- `fromJson` stays once on the `Message` base and is part of the public surface — a caller parses a known message type with `OkMessage::fromJson(...)`. Do not re-add it to the family bases or leaves, and do not remove it in favour of the deserialiser, which answers a different question.
