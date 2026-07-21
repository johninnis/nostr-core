# 48. The protocol message hierarchy is an inheritance sum type with a backed-enum discriminant

## Status

Accepted.

Supersedes ADR-0016. That record decided two things at once — that the message families are modelled as an inheritance sum type, and that each leaf's variant is identified by a `protected const string TYPE` surfaced through `getType(): string`. The structural decision is unchanged and is carried forward here verbatim; the discriminant mechanism is revised from the string constant to a backed enum. This record now holds the complete, current decision so a reader does not have to assemble it from two records.

## Context

The relay protocol is a closed set of message types in two families — client-to-relay (`EVENT`, `REQ`, `CLOSE`, `AUTH`, `COUNT`) and relay-to-client (`EVENT`, `OK`, `EOSE`, `CLOSED`, `NOTICE`, `AUTH`, `COUNT`). Two questions arise together: how to model that closed set of shapes in a language with no sealed classes, and how each shape should announce which variant it is so a consumer can dispatch on it exhaustively.

The codebase models the families with inheritance:

- An abstract `Message` base owns the one genuinely shared step — the decode in `tryFromJson()` and the `encode()` wire-flags helper — and declares the contract every message satisfies (`type`, `toArray`, `tryFromArray`).
- Abstract `ClientMessage` and `RelayMessage` extend it, each owning its family's `toJson()` strategy.
- The concrete leaves (`Client\ReqMessage`, `Relay\OkMessage`, …) are `final`, each carrying its own data and `toArray`/`tryFromArray`.
- One step deeper, `Client\FilterRequestMessage` is an abstract base owning the complete REQ/COUNT mechanism; `ReqMessage` and `CountMessage` are `final` leaves supplying only their variant.

This is inheritance that shares behaviour, which the default rule forbids — behaviour is meant to be shared through an injected collaborator, not a base. Two specific things invite a "simplification" and so are recorded: a base whose leaves carry different data is a discriminated union that can read as reach-for-reuse, and `ReqMessage`/`CountMessage` differing only by their variant can read as needless subclassing an enum would replace.

Separately, a consumer that receives a base-typed message must fan out on which shape it is, and it wants the analyser to force it to handle every shape — forgetting `CLOSED` in a client is a real bug. PHP has no sealed classes and no type patterns, so a `match (true)` over `instanceof` arms is not checked for exhaustiveness, and neither is a `match` over a plain `string` discriminant — the analyser does not know the string ranges over a closed set. The discriminant originally existed only as a `protected const string TYPE` per leaf, surfaced through `getType(): string`. That string is also the wire tag, and it was spelled twice over: once in each leaf's constant and again in every arm of the deserialiser's `match`, and `getType()` itself had no caller.

## Decision

### The families are an inheritance sum type (carried forward from ADR-0016, unchanged)

Keep the inheritance. It is how PHP expresses a closed *sum type* — the same exception that lets an abstract base stand in for the generic a typed collection needs. The language cannot name `Message = Event | Req | Ok | …`, so an abstract base with `final` leaves stands in for the type it cannot write. Three things make this the right model and not the banned reach-for-reuse:

1. **The leaves are distinct nominal types matched at boundaries — but that alone would only need an interface.** `MessageDeserialiserInterface` returns `?ClientMessage`/`?RelayMessage`, so whatever reads a decoded message `instanceof`-matches the concrete leaf to reach its typed fields — a request handler branching over `EventMessage`/`ReqMessage`/`CloseMessage`/`AuthMessage`/`CountMessage`, a connection branching over `OkMessage`/`EoseMessage`/`ClosedMessage`/`NoticeMessage`/`AuthMessage`. Collapsing a family onto one class with a `type` field would turn those typed reads into runtime field-presence checks. Distinct types, though, are what an interface gives; they are not by themselves the reason for a base.

2. **The shared mechanism is a self-typed static constructor a collaborator cannot supply.** The reason this is a base and not an interface-plus-collaborator is `tryFromJson(string): ?static`, defined once on `Message`: `OkMessage::tryFromJson($json)` returns `?OkMessage`. A static, self-typed named constructor is exactly what injection cannot provide — an interface can declare a static method but not carry its body, so every leaf would re-roll the identical decode-then-`tryFromArray`; and a `$serialiser->decode(OkMessage::class, $json)` collaborator forfeits both the `?OkMessage` return and the named-constructor call site. The abstract base is the one PHP construct that gives every leaf an identical, inherited, self-typed parse step without duplication. That is the generic-substitution exception applied to a sum type instead of a collection.

3. **Per-family behaviour stays per family, not on the root.** `toJson()` lives on `ClientMessage` and `RelayMessage`, not `Message`, because the families serialise differently — a relay message may already hold pre-serialised JSON (`PreSerialisedMessageInterface`). It is one method per family, not one duplicated onto the root. The only thing on `Message` itself is the genuinely shared step.

`FilterRequestMessage` is the same exception one level deeper, and earns its own mention because it is a discriminated union by *wire tag*, not the generic case: the base owns the complete mechanism (validation, `type`, `toArray`, `tryFromArray`) and each leaf supplies only its variant. REQ and COUNT must stay separate types a caller can `instanceof`; a single class with `req()`/`count()` named constructors would erase that distinction, and two hand-written classes each forwarding to a shared implementation would only reproduce the base as boilerplate. Constant-only leaves are the smallest shape that keeps them distinct, self-validating types.

`tryFromJson` and the deserialiser answer two different questions over one shared decode step. `Message::tryFromJson` parses a string as a *known* type (`OkMessage::tryFromJson($json)`); `MessageDeserialiserInterface` parses a string of *unknown* type, resolving family and wire tag to the right leaf (the `match` in `JsonMessageDeserialiser`). The decode-then-`tryFromArray` step lives once on the base so neither path re-rolls it.

### The variant discriminant is a backed enum (revised from ADR-0016's string constant)

The message-type discriminant is a backed enum — `RelayMessageType` (seven cases) and `ClientMessageType` (five), each case backed by its wire tag. Each hierarchy's base declares `abstract public function type(): <Enum>`, so every leaf must return its case, and a consumer dispatches with `match ($message->type())` over the enum — which the analyser checks for exhaustiveness.

The enum is the single source of truth for the tag. The per-leaf `TYPE` constants, the base `getType(): string`, and the magic strings in the deserialiser are removed; the deserialiser matches `Enum::tryFrom($tag)` (unknown wire input falling to `null`), and any caller needing the wire string reads `type()->value`. The two hierarchies keep separate enums even where the wire tags coincide (`EVENT`, `AUTH`, `COUNT`), because a client `EVENT` and a relay `EVENT` are different types carrying different payloads.

The enum complements the inheritance sum type; it does not replace it. The leaf classes remain the sum type — they hold the payloads and behaviour — and the enum is only the analyser-checkable tag over them.

## Consequences

- Base classes are abstract; leaves are `final`. Adding a message is adding a leaf (which the abstract `type()` forces to declare its enum case), plus a deserialiser arm for inbound routing.
- Dispatch over message type is analyser-checked: adding a leaf forces every exhaustive `match ($message->type())` to grow a case or fail to compile.
- The wire tag is named once, in the enum, instead of in each leaf and each deserialiser arm. A consumer that read `getType()` reads `type()->value` instead; there is one way to name a message's type.
- Do not collapse a family onto one class with a `type` field, and do not turn `ReqMessage`/`CountMessage` into a single parameterised class — each removes a type that a caller matches on with `instanceof`. (The *discriminant* is an enum; the *variants* are still distinct leaf classes.)
- Do not "extract the shared serialisation into a collaborator" — `tryFromJson` is a self-typed static named constructor a collaborator cannot supply without per-leaf duplication or losing its return type.
- Do not hoist `toJson()` onto `Message`; the two families serialise differently.
- `tryFromJson` stays once on `Message` and is part of the public surface; the deserialiser answers the different unknown-type question. Keep both.
- A test pins that each leaf reports its enum case and that the deserialiser round-trips the tag; it fails if a leaf's `type()` drifts from its wire tag.
