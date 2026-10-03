# 79. A zap receipt holds the one zap request it carries, and verification takes the receipt

## Status

Accepted

Supersedes ADR-0062. Taking the LNURL provider key as an argument, returning a sealed failure, checking the zap request's own signature and running comparisons before signatures are carried forward; they are now part of the shared decision, nostr-adrs ADR-0038. This record revises where the structural rules live and what the verifier is handed, and holds what is particular to this package.

## Context

nostr-adrs ADR-0038 decides how every implementation reads and verifies a NIP-57 receipt: one reader, the sender taken from the zap request, parsing that verifies nothing, and verification against a provider key the caller supplies.

This package had two readings. `ZapReceipt::tryFromEvent` took the first decodable `description` tag; `ZapReceiptVerifier` refused a receipt with more than one, with a fence explaining that picking the first would let a receipt carry one request for the verifier and another for the next reader — and `ZapReceipt` was that next reader. BOLT-11 extraction and the amount comparison were written in both. The verifier was handed a bare `Event`, so a receipt it approved and the `ZapReceipt` a consumer displayed could describe different zaps, and the sender shown was the receipt's unverified `P` tag.

## Decision

- **`ZapReceipt::tryFromEvent` is the one reader** of nostr-adrs ADR-0038. It returns `null` for anything that is not a receipt by that record's rule and holds the receipt event, the parsed zap request and the amount (`ZapAmount::tryFromBolt11`, ADR-0083). `description` and `bolt11` are read with `TagCollection::getSoleValueByType`, so order never decides (ADR-0092).
- **`getSenderPubkey()` returns the zap request's pubkey.**
- **`ZapReceiptVerifier::verify(ZapReceipt, PublicKey $lnurlProviderPubkey, ?string $expectedLnurl = null): ?ZapReceiptVerificationFailure`** runs the checks in the shared order and compares the lnurl with `strcasecmp`. The structural cases the parser owns (wrong kind, missing, duplicate or malformed request, unreadable or mismatched amount) are not failure cases.
- **Failures are returned**, as a sealed `ZapReceiptVerificationFailure`, so ignoring one is an analyser error (ADR-0089).

## Consequences

- Breaking: `verify()` takes a `ZapReceipt`; seven failure cases are removed; `getSenderPubkey()` is non-null; `getReceipt()` and `getZapRequest()` are added.
- `SECURITY.md` says a parsed receipt is unverified until `verify()` passes with the provider key.
- Do not add LNURL fetching to make the call self-contained, and do not fold verification into the parser.
- `Nutzap` is not given the same treatment here. NIP-61's observer checks (the recipient's `kind:10019` lists the mint, each proof is P2PK-locked to the key it names, the `u` tag is one of its mints, each proof's DLEQ proof verifies) "can be done offline", but they need the recipient's `kind:10019` and the mint's keyset, which this package does not hold; a nutzap verifier would take them as arguments, as `verify()` takes the provider key. Whether a proof is still unspent is knowable only from the mint.
- Shared decision: nostr-adrs ADR-0038.
