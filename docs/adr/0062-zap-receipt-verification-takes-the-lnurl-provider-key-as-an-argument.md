# 62. Zap receipt verification takes the LNURL provider key as an argument rather than fetching it

## Status

Accepted

## Context

`ZapReceipt::tryFromEvent` parses a kind 9735 event into sender, recipient, amount and message. It
verifies nothing. Anyone can publish a kind 9735 event, and its `description` tag — an arbitrary JSON
blob from which the *sender* is read — is entirely attacker-chosen. A consumer rendering "X sent you N
sats" from a parsed receipt has a spoofing vector, and zaps carry real money.

NIP-57 Appendix F states what a client must check:

> - The `zap receipt` event's `pubkey` MUST be the same as the recipient's lnurl provider's `nostrPubkey`
> - The `invoiceAmount` contained in the `bolt11` tag of the `zap receipt` MUST equal the `amount` tag of the `zap request`
> - The `lnurl` tag of the `zap request` (if present) SHOULD equal the recipient's `lnurl`

The first and third compare the receipt against values that live in the recipient's LNURL endpoint
configuration. This package does not fetch LNURL configuration and should not start: that is a second
HTTP-shaped capability with its own SSRF surface, and it would drag the LNURL protocol into a library
whose scope is Nostr events.

The obvious conclusion — that verification therefore cannot live here — is wrong, and is what left
this surface unverified. The library cannot *obtain* the provider key, but it can *enforce* the
comparison once given it. Leaving the whole of Appendix F to consumers means each one reimplements
signature checks, tag extraction and amount comparison, and the second requirement needs no external
input at all.

## Decision

`ZapReceiptVerifier` applies Appendix F, taking the values it cannot know as arguments:

```
verify(Event $receipt, PublicKey $lnurlProviderPubkey, ?string $expectedLnurl = null): ?ZapReceiptVerificationFailure
```

- **The provider key is a required argument, not a default.** It is the root of trust: with the wrong
  one, a receipt from any provider verifies. Making it required forces the caller to have obtained it,
  and there is no value the library could safely supply on their behalf.
- **The lnurl is optional**, matching its SHOULD status. When supplied it is compared; when absent that
  check is skipped rather than silently passing something unchecked.
- **Failures are returned, not thrown**, as a sealed `ZapReceiptVerificationFailure` — a hostile
  receipt is an anticipated outcome on untrusted input, and a nullable return makes ignoring it an
  analyser error.
- **The zap request's own signature is verified too**, which Appendix F does not list. The sender a
  consumer displays comes from that embedded request; without this check the three Appendix F
  conditions can all hold while the provider attributes the zap to anyone. Verifying it proves the
  claimed sender authorised this request.
- **Structural checks precede the two signature verifications**, so a malformed receipt costs no
  elliptic-curve work — the ordering `EventValidator` already uses.
- **`ZapReceipt::tryFromEvent` stays a parser.** It is not made to verify: it has no provider key and
  no signature service, and quietly acquiring either would put a collaborator inside a value object.

## Consequences

- A consumer holding the recipient's LNURL configuration can enforce NIP-57 without writing the checks
  themselves, and the failure tells them which condition broke.
- Verification remains impossible without the provider key. That is the protocol's shape, not a gap in
  this package — and `SECURITY.md` says so rather than implying a parsed receipt is trustworthy.
- **Do not add LNURL fetching to make the call "self-contained".** It would add an outbound HTTP
  capability, and the SSRF obligations that come with it, to satisfy an argument the caller already
  has.
- **Do not fold verification into `ZapReceipt::tryFromEvent`.** Parsing an untrusted event and
  authenticating it are separate steps, and a parser that sometimes verifies invites callers to assume
  it always does.
- Each Appendix F condition, the kind gate and the zap-request signature check are pinned by tests
  proven to fail when that condition is removed.
- `Nutzap` is deliberately not given the same treatment: whether a Cashu proof is genuine and unspent
  is knowable only by redeeming it at a mint, which is not a comparison this package can be handed.
