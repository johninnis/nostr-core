# 106. A deletion request is built only for the author's own event, and never for a deletion request

## Status

Accepted

The rules are the shared decisions nostr-adrs ADR-0086 and ADR-0093; this record holds what is particular to this package.

## Context

nostr-adrs ADR-0086 decides for every implementation that a deletion request is built only for a target its author published, because NIP-09 makes every compliant reader ignore a reference to another author's event, and a builder that writes one shows its caller a deletion nobody honours.

NIP-09: "Publishing a deletion request event against a deletion request has no effect." nostr-adrs ADR-0093 decides that such a request is still a valid event, and that whoever applies deletions ignores that target; a builder that writes one shows its caller a deletion that does nothing, for the same reason as above.

`RumourFactory` is built for one author (ADR-0105) and `createDeletion` takes the published event it deletes (nostr-adrs ADR-0075), so the factory holds both pubkeys and can tell.

## Decision

`RumourFactory::createDeletion(Event $target)` throws `InvalidArgumentException` when the target's pubkey is not the factory's author. The target is a value the caller already holds, so naming another author's event is a programmer error, not an anticipated outcome. It throws the same way when the target is itself a deletion request (kind 5).

Reading is the other side of the same rule: `NipComplianceValidator::validateNip09Compliance` accepts a deletion request whose `k` tag names kind 5, because the event is valid NIP-09 and only its effect is nil (nostr-adrs ADR-0093).

## Consequences

- Every deletion request this package builds names only events its author can delete, and never a deletion request.
- Do not relax the check to let a caller publish a request for an event it does not own.
- Do not refuse a received deletion request for naming a deletion request; ignore that target where deletions are applied.
- Shared decisions: nostr-adrs ADR-0086 and ADR-0093.
