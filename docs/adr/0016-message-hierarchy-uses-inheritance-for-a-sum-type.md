# 16. The protocol message hierarchy uses inheritance for a discriminated union

## Status

Accepted

## Context

The NIP-01 relay protocol is a closed set of message types split into two families — client-to-relay (`EVENT`, `REQ`, `CLOSE`, `AUTH`, `COUNT`) and relay-to-client (`EVENT`, `OK`, `EOSE`, `CLOSED`, `NOTICE`, `AUTH`, `COUNT`). The codebase models these with inheritance:

- An abstract `Message` base owns the one shared mechanism — `encode()` (the `JsonWireFormat::MESSAGE` serialisation) — and declares the contract every message satisfies (`getType`, `toArray`, `fromArray`).
- Abstract `ClientMessage` and `RelayMessage` extend it. Each is the nominal discriminator for its family and owns that family's `toJson()` strategy: a client message always encodes its array; a relay message returns its pre-serialised JSON when it carries one (`PreSerialisedMessageInterface`) and otherwise encodes.
- The concrete leaves (`Client\ReqMessage`, `Relay\OkMessage`, …) are `final` and each carries that message's distinct data and `fromArray`/`toArray` logic.
- One step deeper, `Client\FilterRequestMessage` is itself an abstract base owning the *complete* REQ/COUNT mechanism (validation, `getType`, `toArray`, `fromArray`); `ReqMessage` and `CountMessage` are `final` leaves that supply only `protected const string TYPE` (`'REQ'` / `'COUNT'`) and **zero** behaviour.

Both shapes are exactly the ones the conventions name as "merely resembling" the one sanctioned generics-substitution use of inheritance and therefore needing a record rather than an assumption (`CODING-CONVENTIONS.md` §3, `PHP-CONVENTIONS.md` "Composition over inheritance"): `Message`/`ClientMessage`/`RelayMessage` is a **discriminated union** (the "`Message` sum type" the rule cites by name), and `FilterRequestMessage` → `ReqMessage`/`CountMessage` is **two implementations differing by a single value**. Neither is the `TypedCollection` generics case — there is no `Collection<T>` PHP cannot express to name here.

## Decision

Keep the inheritance, recorded here as a deliberate sum-type modelling decision rather than the generics-substitution exception.

- The leaves are **distinct nominal wire types used at boundaries.** `MessageDeserialiserInterface` returns `?ClientMessage` / `?RelayMessage` and dispatches on the wire tag to a concrete leaf; consumers `match`/`instanceof` on the concrete type. Collapsing a family to one class carrying a `type` field would erase the types the deserialiser and consumers rely on.
- For `FilterRequestMessage`, the base is a **complete shared mechanism** and the leaves differ only by the wire tag. Re-expressing this as composition (a single class with `req()`/`count()` named constructors) would still need `ReqMessage` and `CountMessage` to exist as distinct boundary types, so composition would only reproduce the base through forwarding boilerplate — the banned thin-wrapper. The abstract base owning the mechanism with constant-only leaves is the smaller design.
- `toJson()` lives on `ClientMessage` and `RelayMessage`, not on `Message`, because the two families genuinely differ (relay messages may be pre-serialised). It is not hoisted to remove an apparent duplication that is not one.
- Two distinct JSON entry points share one decode helper rather than duplicating it. `Message::fromJson` (defined once on the base, inherited by every leaf) is the **type-safe known-message** parse a consumer reaches for when it expects a specific message — `OkMessage::fromJson($json)` returns `?OkMessage`. `MessageDeserialiserInterface` / `JsonMessageDeserialiser` is the **dispatch-on-unknown-type** parse for inbound routing, resolving the client-vs-relay family and the wire tag. These are different operations, not two ways to do one thing; the shared `decodeArray`-then-`fromArray` prelude lives once on the base, so the per-leaf `fromJson` is not re-rolled in every class.

## Consequences

- Base classes are abstract; leaves are `final`. Adding a new message is adding a leaf plus a deserialiser arm.
- Do not collapse a message family onto a single class with a `type` field, and do not convert `ReqMessage`/`CountMessage` into an enum or a single parameterised class — it removes the distinct boundary types the deserialiser and consumers match on.
- Do not hoist `toJson()` onto `Message`; the per-family strategies differ.
- `fromJson` lives once on the `Message` base; do not re-duplicate it onto the family bases or the leaves. It is retained public API (consumers such as nostr-relay parse a known message type with `OkMessage::fromJson(...)`), distinct from the deserialiser's dispatch-on-unknown-type role.
- Tests pin each leaf's wire shape (`toArray`/`fromArray` round-trips), so converting the leaves away from distinct types fails the suite.
