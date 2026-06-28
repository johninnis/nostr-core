# 42. Entities are identity-bearing artifacts; `Filter` is a value object

## Status

Accepted

## Context

`Domain/Entity/` is reserved for identity-bearing concepts — those carrying a continuous identity or a
lifecycle — while everything defined wholly by its attributes lives in `Domain/ValueObject/`. Three core
protocol nouns sit close enough to that line to be mistaken for one another, and one was originally
misfiled:

- `Event` carries an `id` that is the SHA-256 of its own fields.
- `Filter` carries no id, but `FilterHasher` derives a stable, order-independent digest from its fields.
- `Subscription` carries a `SubscriptionId` that is an externally assigned label.

The tempting (and incorrect) shortcut is to decide entity-versus-value by *how prominent* a concept is,
or by *whether it has a hash identity*. Both fail. Prominence is irrelevant — `PublicKey` and `EventId`
are as central as anything and are values. And a hash identity decides nothing: `EventId` is a content
hash and `Event` is still an entity, exactly as a Git commit is content-addressed yet an object with a
lifecycle. By the hash test alone `Filter` (hashed by `FilterHasher`) and `Event` (hashed into
`EventId`) would be indistinguishable, which is what led `Filter` to be filed under `Entity/` next to
`Event`.

The protocol settles it. The base flow specification states that the only object type that exists is the
event; a filter is "a JSON object that determines what events will be sent in that subscription" — a
query parameter of a request, never stored, never referenced by an id, never mutated. A subscription is
a per-connection relationship named by an arbitrary, non-globally-unique `subscription_id`, opened by a
request and stopped by a close.

## Decision

The entity-versus-value boundary is drawn by **what the concept is**, not by how its identity is
computed:

- An **entity** is an identity-bearing artifact with a lifecycle — either *the* protocol object, or a
  thing with an **assigned** identity whose attributes change while it stays "the same thing".
  - `Event` — the sole protocol object: stored, referenced by other events, deletable, expiring. Its
    content-hash `id` does not make it a value; its artifact role makes it an entity.
  - `Subscription` — its identity (`SubscriptionId`) is **assigned**, not derived from its filters, and
    it moves through a state lifecycle (pending → open → closed). Two subscriptions with identical
    filters but different ids are different subscriptions; the same id with new filters is the same
    subscription, changed. A weak, per-connection, non-unique id is still identity.
- A **value object** is defined entirely by its attributes — including ids and query specifications.
  - `Filter` — a query specification (the Specification pattern): a predicate over events, a request
    parameter, with no id, no storage, no references, no lifecycle. It is a value object and lives in
    `ValueObject/Protocol/`, beside `RelayUrl`, `SubscriptionId`, `Nip11Info`, and the message frames.
  - `EventId`, `SubscriptionId` — an id is always a value; the entity is the thing the id names.

## Consequences

- `Filter` moves from `Domain/Entity/` to `Domain/ValueObject/Protocol/`. It was the one misfiled noun.
- The criterion is recorded once, so the recurring question — "Event and Filter are both core and both
  hashable, why is one an entity and the other a value?" — has a written answer instead of being
  re-litigated. The answer is: artifact-with-identity-and-lifecycle versus attribute-defined
  specification; content-addressing is orthogonal to the distinction.
- `Subscription` stays in `Entity/` — it is the least ambiguous entity in the package, precisely because
  its identity is assigned rather than content-derived. Do not "demote" it on the grounds that its id is
  weak, and do not "promote" `SubscriptionId` (or `EventId`): an id is a value.
