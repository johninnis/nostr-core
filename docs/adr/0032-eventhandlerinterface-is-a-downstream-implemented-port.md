# 32. `EventHandlerInterface` is a downstream-implemented port with no in-package implementer or consumer

## Status

Accepted

## Context

This package owns the shared Nostr domain vocabulary — `Event`, `SubscriptionId`, `RelayUrl` — and the ports that depend on it. `EventHandlerInterface` is the callback contract a relay-connection driver implements to receive, per subscription, the events a relay streams plus the end-of-stored-events, closed, and notice signals. The events it delivers are this package's domain types, and more than one consumer package drives subscriptions, so the handler shape is defined once here for every consumer to share; redefining it per consumer would fork that shape.

The package itself performs no relay I/O and runs no connection loop. Nothing inside it implements or calls this port — it is referenced only by downstream packages (a client package's connection manager implements it; its connection layer calls it). To an audit scoped to this repository the interface therefore reads as orphaned: a search finds zero in-package references and the obvious move is to delete it. That conclusion is wrong — deleting it breaks every consumer's connection layer.

This is the one port in the package with no in-package touch at all. The other host-implemented ports are visibly live by contrast: `HttpServiceInterface` is injected into `Nip05Verifier` and `Nip11Client`, and `Nip98ReplayGuardInterface` is injected into `Nip98Validator`, so a search finds each as a dependency type even though neither has an in-package implementer. `EventHandlerInterface` is neither implemented nor consumed here, which is why it alone looks dead.

## Decision

Keep `EventHandlerInterface` defined in this package's Application ports despite the absence of any in-package implementer or consumer. Its implementer and its caller are downstream connection-driving packages. Do not treat zero in-package references as evidence it is unused, and do not delete it.

## Consequences

- A repository-scoped dead-code search will always show `EventHandlerInterface` with no references. That is the expected shape of a published port whose only implementer and caller live downstream, not a defect.
- A one-line fence on the interface points here, so a reviewer stops before "removing the orphan" — the check that matters is downstream usage, not an in-package grep.
- If the package ever grows code that itself drives subscriptions, the port gains an in-package consumer and this note becomes moot; the contract stays defined here regardless, so consumers keep sharing one handler shape.
