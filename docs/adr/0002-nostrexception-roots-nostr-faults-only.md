# 2. `NostrException` roots Nostr faults; consumers root their own

## Status

Accepted

## Context

`NostrException` (abstract, extending `\Exception`) is defined here and is the **shared** root for faults thrown by Nostr library code across the whole `nostr-*` ecosystem — not just this package. nostr-core's own leaves extend it (`InvalidEventException`, the crypto faults `CryptoException` / `EcdhException` / `EncryptionException` / `GiftWrapException`, and the key-lifecycle and NIP-49 faults `SecretKeyMaterialZeroedException` / `Nip49DecryptionFailedException`). A sibling `nostr-*` package that throws its own package-specific faults extends `NostrException` too — that shared root is correct and intended for Nostr library code.

The tempting-but-wrong move is at a different boundary: the **consumer application**. Because an application depends on nostr-core, it looks natural to root the application's *own* faults under `NostrException` as well, so the whole process shares one throwable root. That is the case this record rejects.

## Decision

Faults are rooted by **whose code raises them, not by the dependency graph** — and the line is drawn between *Nostr library code* and a *consumer application*, not "anything that depends on nostr-core".

- A fault thrown by **any `nostr-*` package's library code** roots at `NostrException`, the shared ecosystem base defined here. A `nostr-*` package defines its own abstract base extending `NostrException` only if it throws package-specific faults.
- A **consumer application** roots its OWN faults at its OWN independent base, never under `NostrException`. Hubstr, for instance, throws a `HubstrException` that extends `\Exception` directly and does NOT extend `NostrException`, even though it depends on the Nostr libraries.
- What decides the root is the authoring code — Nostr library vs consumer application — not what it imports. Depending on nostr-core does not pull an application's exceptions under `NostrException`; only faults raised by Nostr library code belong there.

## Consequences

- A `catch (NostrException)` catches faults from any `nostr-*` library across the process, and never an application-originated fault; the root identifies the origin.
- A new `nostr-*` package extends `NostrException` for its package-specific faults; a new consumer application defines its own root.
- Base exceptions are abstract; leaf exceptions are `final`.
- Do not root an application's exceptions under `NostrException` to "share one root", and do not give a `nostr-*` library package its own disconnected root — the authoring code (library vs application) decides, not the dependency direction.
