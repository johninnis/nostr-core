# 23. Typed collections live in one `Collection/` directory, grouped by structural kind

## Status

Accepted

## Context

The domain wraps every multi-element value in a typed collection class. Those classes used to be scattered across three homes: the shared `TypedCollection` base sat in `Domain/Collection/`, while each concrete collection lived beside the type it collects — `TagCollection` in `ValueObject/Tag/`, `EventIdCollection` in `ValueObject/Identity/`, `EventCollection` and `FilterCollection` in `Entity/`, and so on. One kind of construct, three locations, and a base class separated from every one of its subclasses.

The instinct that produced the scattering was co-location: keep a collection next to its element so the noun and its plural read together. That instinct is reasonable on its own, but it conflicts with how the rest of the domain is organised, because the domain uses two different grouping axes and co-locating collections mixes them:

- **The top level groups by structural kind / architectural role** — `Entity`, `ValueObject`, `Enum`, `Service`, `Failure`, `Exception`, `Factory`. Each answers "what kind of construct is this?", and it groups by that kind *regardless of domain concept*: every enum is in `Enum/`, every fault in `Exception/`, with no further concept split.
- **The sub-level under `ValueObject/` groups by domain concept** — `Identity`, `Payment`, `Protocol`, `Reference`, `Content`, `Tag`. Each answers "what part of the domain is this?".

A typed collection's primary identity is structural: it *is* a collection. Filed inside a `ValueObject/<concept>/` folder it sits on the concept axis, where it does not belong — the element it wraps is the concept; the collection is a container shape *over* that concept. Worse, a collection of entities (`EventCollection`) is not a value-object concept-peer at all, so housing it under `ValueObject/` or beside an entity misstates what it is. The mismatch is what made the old layout read as three arbitrary homes.

## Decision

All typed collections live in one top-level `Domain/Collection/` directory — the `TypedCollection` base and every concrete leaf together, flat, with no concept sub-folders. `Collection/` is a structural-kind bucket and a peer of `Entity/`, `ValueObject/`, `Enum/` and the rest, grouping its kind regardless of domain concept exactly as those do.

This puts `Collection` on the structural axis where it belongs, and reunites the generic-substitution hierarchy: the abstract base and all its leaves sit in one place, where the whole family — including the one leaf that deliberately does *not* extend the base, the keyed-map `SubscriptionCollection` (see ADR-0007) — is visible and auditable at once. Only the collections move; the element types stay concept-grouped under `ValueObject/` and `Entity/`. The two axes stay clean: a type's concept decides where the type lives, its being-a-collection decides where its collection lives.

The directory stays flat on purpose. Sub-grouping it by concept (`Collection/Reference/`, `Collection/Identity/`) would re-import the concept axis into a structural folder — the very mixing this decision removes — and duplicate the concept taxonomy in two places.

## Consequences

- There is one obvious home for every collection; "where does this collection live?" has a single answer, matching how `Enum`/`Exception`/`Failure` already work.
- The `TypedCollection` base sits with its leaves, so the substitution family — and ADR-0007's deliberate exception to it — reads in one directory instead of being reconstructed from several.
- A collection and the type it wraps no longer share a folder. That lost adjacency is the cost, and it is the same cost the codebase already pays for enums (a marker enum lives in `Enum/`, not beside what it marks), so the trade is consistent rather than novel.
- `Collection/` is now a populated top-level domain directory rather than an orphan folder holding a lone base — earned scaffolding, not empty scaffolding.
- Consumers that imported a collection from its old namespace must update to `Domain\Collection\`. This is a deliberate breaking namespace change, released as such, not an internal refactor.
- Keep the directory flat. A future urge to sub-group it by concept is the concept axis leaking back in — resist it, or supersede this record rather than mixing the two axes.
