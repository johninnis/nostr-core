# 36. `Nip11Info` is a thin typed view over the raw document, while `ProfileMetadata` is fully parsed

## Status

Accepted

## Context

This package models two kinds of relay/identity metadata, and it models them in opposite ways — which
reads as an inconsistency someone forgot to resolve.

- `ProfileMetadata` (kind-0 user metadata) parses every field into a typed property in `fromArray` and
  exposes settled values. Its shape is a closed set of named scalars (name, about, picture, banner,
  nip05, lud16, …); each field has a single meaning and a natural type, and there is a finite, known
  list of them.
- `Nip11Info` (relay information) stores the decoded document as a raw array and projects each field on
  access. It parses nothing eagerly and holds no typed field except the `RelayUrl` it was fetched from.

A reader who sees `ProfileMetadata` fully parsed will read `Nip11Info`'s twenty `JsonWireFormat::*Field`
projections as an unfinished value object and be tempted to "complete" it by parsing every field into
typed properties to match its sibling. The two designs are deliberate, and the difference is in the
shape of the data, not the effort spent.

The relay information document is open and extensible: alongside a few scalars it carries several
free-form nested structures — retention policies, fee schedules, country and language lists,
operator-defined tags — that have no single shape across relays and no domain type in this package.
Parsing those eagerly would mean inventing a value object per advisory sub-structure, types most
consumers never read and which would ossify a document the protocol intends to stay open. A closed
scalar set rewards full parsing; an open advisory document does not. The one field that does carry a
genuine domain type — the operator public key — is parsed at its accessor and returned as that type, so
the boundary is honoured exactly where a type exists.

## Decision

`Nip11Info` stays a thin typed view: it keeps the raw decoded document and projects fields through typed
accessors, returning the value object (`PublicKey`) only for the field that has one. It is not converted
to an eagerly fully-parsed value object, and no value objects are invented for its free-form
sub-structures. `ProfileMetadata` stays fully parsed. The divergence is intended: parse eagerly when the
field set is closed and every field is typeable; keep a raw view when the document is open and advisory.

## Consequences

- The two metadata types look inconsistent at a glance and are meant to. The fence and this record stop
  a well-meaning "finish `Nip11Info` like `ProfileMetadata`" change that would invent throwaway types
  for advisory data and fight the protocol's extensibility.
- Accessors re-project from the raw document on each call rather than reading a settled field. For a
  document fetched once and read a handful of times this cost is irrelevant, and it keeps the type
  immutable without a memoised field per accessor.
- A future field that gains a real domain type is parsed at its accessor and returned as that type, the
  way the operator public key already is — without flipping the whole object to eager parsing.
