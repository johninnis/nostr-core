# 75. The protocol message hierarchy is an inheritance sum type that parses and serialises once on the base

## Status

Accepted

Supersedes ADR-0048. The inheritance sum type and the backed-enum discriminant are carried forward unchanged. This record revises three things: the list-shape and wire-tag checks move from every leaf onto the base, serialisation moves onto `Message` because the two families no longer serialise differently, and the pre-serialised relay path is removed. It holds the complete current decision.

## Context

The relay protocol is a closed set of message types in two families — client-to-relay (`EVENT`, `REQ`, `CLOSE`, `AUTH`, `COUNT`) and relay-to-client (`EVENT`, `OK`, `EOSE`, `CLOSED`, `NOTICE`, `AUTH`, `COUNT`). PHP has no sealed classes, so the closed set is modelled as an abstract `Message` base, abstract `ClientMessage` and `RelayMessage` family bases, and `final` leaves, with `Client\FilterRequestMessage` one level deeper owning the REQ/COUNT mechanism for its two constant-only leaves. Each leaf reports its variant through a backed enum (`ClientMessageType`, `RelayMessageType`) so a consumer's `match ($message->type())` is checked for exhaustiveness.

Three things had drifted. Every leaf's `tryFromArray` repeated the same two checks — that the input is a JSON list, and, after building the value, that its first element is the leaf's wire tag — eleven copies of one rule. The two families each carried their own `toJson()` only because a relay `EVENT` could splice an event's stored input JSON onto the wire (`PreSerialisedMessageInterface`), and that splice re-emitted bytes the event had never vouched for (ADR-0076). And because the discriminant was an instance method, the tag could only be checked after a leaf had already been constructed.

## Decision

### The families are an inheritance sum type (carried forward)

Keep the inheritance. It is how PHP expresses a closed sum type, and it is not the reach-for-reuse that inheritance usually is:

- The leaves are distinct nominal types a caller `instanceof`-matches to reach typed fields. Collapsing a family onto one class with a type field would turn those typed reads into runtime field-presence checks.
- Distinct types alone would need only an interface. The base exists because it supplies the one thing a collaborator cannot: a self-typed static named constructor, `OkMessage::tryFromJson($json): ?OkMessage`, defined once and answering for whichever class it is called on. An interface can declare a static method but not carry its body, and a `$serialiser->decode(OkMessage::class, $json)` collaborator forfeits both the `?OkMessage` return and the named-constructor call site.
- `FilterRequestMessage` is the same shape one level deeper, a discriminated union by wire tag: the base owns the complete REQ/COUNT mechanism and `ReqMessage` and `CountMessage` supply only their variant. They stay separate types a caller can `instanceof`; constant-only leaves are the smallest shape that keeps them distinct.

### The discriminant is a backed enum, reported statically

`type()` is a `public static` method returning the leaf's enum case; `ClientMessage` and `RelayMessage` narrow its return to their own enum. Being static, it can be read before a value exists, which is what lets the base check the wire tag once. It is still callable as `$message->type()`, so exhaustive dispatch reads as before, and a caller needing the wire string reads `type()->value`. The two families keep separate enums even where tags coincide, because a client `EVENT` and a relay `EVENT` carry different payloads.

### The base parses and serialises; the leaf supplies only its payload

- One parse step, `Message::tryFromTaggedList`, is `final protected`. It narrows the input to a list whose element 0 is a string, asks the family's `messageClassFor(tag)` which leaf owns that tag, keeps it only if that leaf is the called class or one of its subclasses, and hands the remaining elements to the leaf's `protected static tryFromPayload(list<mixed>)`. No leaf repeats either check.
- Each family exposes it as `final public static tryFromArray(mixed): ?static` and `tryFromJson(string): ?static`, and resolves a tag through its own enum in `messageClassFor`. The same two entry points serve every class they are called on, because `static` is the class the caller named:
  - `ClientMessage::tryFromJson($json)` / `RelayMessage::tryFromJson($json)` parse a message of unknown type and return whichever leaf its tag names;
  - `OkMessage::tryFromJson($json)` parses a message known to be one type and returns `?OkMessage`, refusing any other tag;
  - `FilterRequestMessage::tryFromJson($json)` returns a `ReqMessage` or `CountMessage` and refuses the rest.
  A parse entry point never fails on the class it is called on: there is none on `Message`, which cannot tell a client `EVENT` from a relay `EVENT`, and no entry point calls the abstract `type()`. `FilterRequestMessage::tryFrom`/`from` are different: they construct the class they are called on, so they are called on `ReqMessage` or `CountMessage`. PHP cannot refuse a static call on an abstract class, and calling them on the base is a programming error PHP raises as an `Error`, as it does for `new` on an abstract class.
- `Message::toArray()` is `final` and prepends the tag to the leaf's `protected toPayload()`.
- `Message::toJson()` is `final` and encodes `toArray()`. There is no per-family `toJson()` and no pre-serialised path; `PreSerialisedMessageInterface` is removed.
- There is no separate deserialiser service. A `JsonMessageDeserialiser` beside the leaves' own `tryFromJson` was a second way to parse the same wire string, and its tag-to-leaf table now lives once, in each family's `messageClassFor`.

A leaf whose data carries an invariant (`Client\AuthMessage`, `NoticeMessage`, `FilterRequestMessage`) has a private constructor and one parser that owns the rule, per ADR-0077; its `tryFromPayload` reaches that parser.

## Consequences

- Adding a message is adding a leaf that returns its enum case, parses its payload and writes it, plus an arm in its family's `messageClassFor`, which the enum's exhaustive `match` forces.
- A leaf cannot forget the list or tag check, because it never sees the envelope.
- Relay fan-out re-encodes each event rather than splicing stored bytes. A relay that serialises one event for many subscribers keeps that string itself; the library does not keep one for it.
- Breaking: `type()` is static; `PreSerialisedMessageInterface`, `MessageDeserialiserInterface` and `JsonMessageDeserialiser` are gone, and a message of unknown type is parsed with `ClientMessage::tryFromJson` or `RelayMessage::tryFromJson`; the invariant-bearing leaves have private constructors.
- Do not collapse a family onto one class with a type field, do not turn `ReqMessage`/`CountMessage` into one parameterised class, and do not move the list or tag check back into leaves.
- Do not extract the shared parse step into a collaborator: `tryFromJson` is a self-typed static named constructor a collaborator cannot supply without per-leaf duplication or losing its return type. (Carried forward.)
- Tests pin that each leaf reports its enum case, that each family's entry point returns the leaf its tag names and refuses an unknown or other-family tag, and that a leaf's or `FilterRequestMessage`'s entry point refuses a tag outside its own subtree.
- Shared decision: nostr-adrs ADR-0008.
