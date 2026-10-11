# 137. `EventKind` gives a kind the name @innis/nostr-core gives it, the defining NIP's name where the two differed

## Status

Accepted

## Context

Both core libraries name the same kinds (nostr-adrs ADR-0109), and a consumer holding both, or porting code from one to the other, reads a kind by its name. Two kinds carried different names in the two cores:

- 10009: `USER_GROUPS_LIST` here, `KIND_SIMPLE_GROUPS_LIST` in @innis/nostr-core. NIP-51 defines it as "Simple groups", the list of NIP-29 groups the user is in; the NIPs' kind table calls it "User groups".
- 31234: `DRAFT_EVENT` here, `KIND_DRAFT_WRAP` in @innis/nostr-core. NIP-37, "Draft Wraps", defines it as "an encrypted storage for unsigned draft events of any other kind"; the kind table calls it "Draft Event". This package's builder for it was already `createDraftWrap`, and @innis/nostr-core's `buildDraftWrap`.

## Decision

- Every kind `EventKind` names carries the name @innis/nostr-core gives it, without that package's `KIND_` prefix. A kind added to either core takes the same name in both, in the same change set.
- Where the two differed, the name the defining NIP gives the kind wins over the kind table's: 10009 is `SIMPLE_GROUPS_LIST` and 31234 is `DRAFT_WRAP`.

## Consequences

- A consumer reads the same name for a kind in either language, and the draft wrap's constant matches its builder.
- `USER_GROUPS_LIST` and `DRAFT_EVENT` are gone; a consumer that used them uses the new names, and the events it builds are unchanged. No innis package used either.
- innis/nostr-relay-selection and @innis/nostr-relay-selection named 31234 `DraftEvent` and `KIND_DRAFT_EVENT`, after this package's earlier name; they now name it `DraftWrap` and `KIND_DRAFT_WRAP`, under their own ADR-0014's rule that a kind takes the name the cores give it.
- Shared decision: nostr-adrs ADR-0109.
