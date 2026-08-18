# 63. The header decode vocabulary is neutral, and the fold returns both failures

## Status

Accepted

## Context

The `Nostr <base64(event)>` Authorization header is one wire format with two protocol
consumers: NIP-98 HTTP auth and Blossom's BUD authorisation. `NostrAuthHeaderCodec::decode`
parses it and can fail five ways, all properties of the wire format, none of any protocol.

`Nip98Validator::validateAuthHeader` folds two operations — parse the wire, validate the
protocol — into one call, and for years the fold's single return vocabulary forced
`Nip98ValidationFailure` to absorb five `Header*` cases mirroring the decode failures,
with a private match translating between the two enums. That absorption taxed every
consumer that wanted the phases separate: one that needs the decoded event before
validation (a Symfony authenticator whose passport must carry the claimed pubkey before
login throttling and signature work) reproduced the private mapping to speak the folded
vocabulary; the other protocol consumer (Blossom) was never NIP-98 at all and only ever
wanted the neutral cases. Collapsing the two enums into `Nip98ValidationFailure` was tried
and rejected: it handed Blossom a protocol-named type for a protocol it does not speak.

## Decision

Each vocabulary owns its domain, and nothing translates:

- **`AuthHeaderDecodeFailure` is the codec's failure**, and it carries its own `message()`
  texts — the wire-format words previously squatting in the `Header*` cases.
- **`Nip98ValidationFailure` sheds the five `Header*` cases.** The protocol enum describes
  protocol outcomes only; an event that decoded cannot have a header problem, so the
  removed cases were unreachable from `validate()` by construction.
- **`validateAuthHeader` returns the honest union**
  `PublicKey|Nip98ValidationFailure|AuthHeaderDecodeFailure`. Both failure enums implement
  `AuthHeaderFailureInterface` (`message(): string`), so a fold caller that only surfaces
  words still branches once.

## Consequences

- Two-phase consumers call `decode` then `validate` and meet each vocabulary where it
  belongs; no mapping exists anywhere because nothing needs translating.
- The fold's honest union is the cost of the convenience, borne by fold callers alone: a
  caller must branch on (or interface over) two failure types instead of one. Both enums
  are string-backed, so `->value` machine codes and `message()` words are available
  whichever failure arrives.
- Removing enum cases and widening a return type is a breaking change; it ships in a minor
  version bump.
- The `Header*` machine codes (`header_too_long`, …) move with their cases: the decode enum
  is backed by the same strings the folded cases carried, so anything that persisted or
  transmitted those codes reads and emits them unchanged.
