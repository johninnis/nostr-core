# 16. The protocol message hierarchy uses inheritance for a discriminated union

## Status

Accepted

## Context

The relay protocol is a closed set of message types in two families — client-to-relay (`EVENT`, `REQ`, `CLOSE`, `AUTH`, `COUNT`) and relay-to-client (`EVENT`, `OK`, `EOSE`, `CLOSED`, `NOTICE`, `AUTH`, `COUNT`). The codebase models them with inheritance:

- An abstract `Message` base owns the one genuinely shared step — the decode in `fromJson()` and the `encode()` wire-flags helper — and declares the contract every message satisfies (`getType`, `toArray`, `fromArray`).
- Abstract `ClientMessage` and `RelayMessage` extend it, each owning its family's `toJson()` strategy.
- The concrete leaves (`Client\ReqMessage`, `Relay\OkMessage`, …) are `final`, each carrying its own data and `toArray`/`fromArray`.
- One step deeper, `Client\FilterRequestMessage` is an abstract base owning the complete REQ/COUNT mechanism; `ReqMessage` and `CountMessage` are `final` leaves supplying only `protected const string TYPE`.

This is inheritance that shares behaviour, which the default rule forbids — behaviour is meant to be shared through an injected collaborator, not a base. Two specific things invite a "simplification" and so are recorded: a base whose leaves carry different data is a discriminated union that can read as reach-for-reuse, and `ReqMessage`/`CountMessage` differing only by a constant can read as needless subclassing an enum would replace.

## Decision

Keep the inheritance. It is how PHP expresses a closed *sum type* — the same exception that lets an abstract base stand in for the generic a typed collection needs. The language cannot name `Message = Event | Req | Ok | …`, so an abstract base with `final` leaves stands in for the type it cannot write. Three things make this the right model and not the banned reach-for-reuse:

1. **The leaves are distinct nominal types matched at boundaries — but that alone would only need an interface.** `MessageDeserialiserInterface` returns `?ClientMessage`/`?RelayMessage`, and consumers `instanceof` the concrete leaf to read its typed fields: the relay's `MessageRouter` branches on `EventMessage`/`ReqMessage`/`CloseMessage`/`AuthMessage`/`CountMessage`, the client's `AmphpRelayConnection` on `OkMessage`/`EoseMessage`/`ClosedMessage`/`NoticeMessage`/`AuthMessage`. Collapsing a family onto one class with a `type` string would turn those typed reads into runtime field-presence checks. Distinct types, though, are what an interface gives; they are not by themselves the reason for a base.

2. **The shared mechanism is a self-typed static constructor a collaborator cannot supply.** The reason this is a base and not an interface-plus-collaborator is `fromJson(string): ?static`, defined once on `Message`: `OkMessage::fromJson($json)` returns `?OkMessage`. A static, self-typed named constructor is exactly what injection cannot provide — an interface can declare a static method but not carry its body, so every leaf would re-roll the identical decode-then-`fromArray`; and a `$serialiser->decode(OkMessage::class, $json)` collaborator forfeits both the `?OkMessage` return and the named-constructor call site. The abstract base is the one PHP construct that gives every leaf an identical, inherited, self-typed parse step without duplication. That is the generic-substitution exception applied to a sum type instead of a collection.

3. **Per-family behaviour stays per family, not on the root.** `toJson()` lives on `ClientMessage` and `RelayMessage`, not `Message`, because the families serialise differently — a relay message may already hold pre-serialised JSON (`PreSerialisedMessageInterface`). It is one method per family, not one duplicated onto the root. The only thing on `Message` itself is the genuinely shared step.

`FilterRequestMessage` is the same exception one level deeper, and earns its own mention because it is a discriminated union by *wire tag*, not the generic case: the base owns the complete mechanism (validation, `getType`, `toArray`, `fromArray`) and each leaf supplies only its `TYPE` constant. REQ and COUNT must stay separate types a caller can `instanceof`; a single class with `req()`/`count()` named constructors would erase that distinction, and two hand-written classes each forwarding to a shared implementation would only reproduce the base as boilerplate. Constant-only leaves are the smallest shape that keeps them distinct, self-validating types.

`fromJson` and the deserialiser answer two different questions over one shared decode step. `Message::fromJson` parses a string as a *known* type (`OkMessage::fromJson($json)`); `MessageDeserialiserInterface` parses a string of *unknown* type, resolving family and wire tag to the right leaf (the `match` in `JsonMessageDeserialiser`). The decode-then-`fromArray` step lives once on the base so neither path re-rolls it.

## Consequences

- Base classes are abstract; leaves are `final`. Adding a message is adding a leaf, plus a deserialiser arm for inbound routing.
- Do not collapse a family onto one class with a `type` field, and do not turn `ReqMessage`/`CountMessage` into an enum or one parameterised class — each removes a type that `MessageRouter` and `AmphpRelayConnection` match on with `instanceof`.
- Do not "extract the shared serialisation into a collaborator" — `fromJson` is a self-typed static named constructor a collaborator cannot supply without per-leaf duplication or losing its return type.
- Do not hoist `toJson()` onto `Message`; the two families serialise differently.
- `fromJson` stays once on `Message` and is part of the public surface; the deserialiser answers the different unknown-type question. Keep both.
