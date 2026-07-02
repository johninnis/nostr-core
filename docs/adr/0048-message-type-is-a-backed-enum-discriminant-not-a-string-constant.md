# 48. Message type is a backed-enum discriminant, not a string constant

## Status

Accepted

## Context

A relay message arrives as one of a closed set of seven shapes (event, ok, eose, closed, notice, auth, count); a client message as one of five. Each is modelled as a `final` leaf of an abstract base — a sum type expressed through inheritance, because the leaves carry different payloads and behaviour.

A consumer that receives a base-typed message must fan out on which shape it is, and it wants the analyser to force it to handle every shape — forgetting `CLOSED` in a client is a real bug. PHP cannot help here: it has no sealed classes and no type patterns, so a `match (true)` over `instanceof` arms is not checked for exhaustiveness, and neither is a `match` over a plain `string` discriminant — the analyser does not know the string ranges over a closed set.

The discriminant existed only as a `protected const string TYPE` per leaf, surfaced through a `getType(): string`. That string is also the wire tag, and it was spelled twice over: once in each leaf's constant and again in every arm of the deserialiser's `match`. `getType()` itself had no caller. This is the mechanism ADR-0016 described; that record's inheritance sum-type decision stands, and only its discriminant mechanism is superseded here.

## Decision

The message-type discriminant is a backed enum — `RelayMessageType` (seven cases) and `ClientMessageType` (five), each case backed by its wire tag. Each hierarchy's base declares `abstract public function type(): <Enum>`, so every leaf must return its case, and a consumer dispatches with `match ($message->type())` over the enum — which the analyser checks for exhaustiveness.

The enum is the single source of truth for the tag. The per-leaf `TYPE` constants, the base `getType(): string`, and the magic strings in the deserialiser are removed; the deserialiser matches `Enum::tryFrom($tag)` (unknown wire input falling to `null`), and any caller needing the wire string reads `type()->value`. The two hierarchies keep separate enums even where the wire tags coincide (`EVENT`, `AUTH`, `COUNT`), because a client `EVENT` and a relay `EVENT` are different types carrying different payloads.

## Consequences

- Dispatch over message type is analyser-checked: adding a leaf forces its `type()` (the base method is abstract) and forces every exhaustive `match` to grow a case or fail to compile.
- The wire tag is named once, in the enum, instead of in each leaf and each deserialiser arm.
- The leaf classes remain the sum type — they hold the payloads and behaviour; the enum is only the checkable tag over them. This complements the inheritance sum type, it does not replace it.
- A consumer that read `getType()` reads `type()->value` instead; the string discriminant is gone, so there is one way to name a message's type.
- A test pins that each leaf reports its case and that the deserialiser round-trips the tag; it fails if a leaf's `type()` drifts from its wire tag.
