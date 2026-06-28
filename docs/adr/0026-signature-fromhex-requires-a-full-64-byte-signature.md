# 26. `Signature::fromHex` requires a complete 64-byte signature

## Status

Accepted

## Context

A BIP-340 Schnorr signature is exactly 64 bytes — a 32-byte `r` followed by a 32-byte `s`. On the
wire a signature is hex, so a well-formed one is exactly 128 lowercase hex characters.

A handful of Nostr producers emit a *shorter* hex string: when the high bytes of `r` (or `s`) happen
to be zero, they strip the leading zero bytes, yielding a 126- or 127-character string that decodes to
fewer than 64 bytes. A lenient parser could accommodate them — accept 126-128 characters and
left-zero-pad a short input back to 128 before constructing the `Signature`.

That accommodation reads helpful, and refusing it looks like a regression to anyone who has seen
those producers' events get dropped. The forces that argue against it:

- **The padding is a guess that can silently reconstruct the wrong signature.** Left-padding only
  recovers the intended value when the stripped bytes came from the front of `r`. A string shortened
  because `s` lost a leading zero pads into a different 64-byte layout — a structurally valid but
  *wrong* signature. The repair cannot tell the two cases apart, so it sometimes fabricates a
  signature the producer never sent.
- **Every reference implementation rejects short signatures.** `libsecp256k1`, `rust-secp256k1`, and
  `@noble/curves` all require the full 64 bytes. Tolerating short input here makes this library the
  outlier and lets a value through that those libraries — and any cross-checking peer — would refuse.
- **A parser of untrusted input should return its failure, not paper over it.** `fromHex` parses
  wire data, so a malformed signature is an anticipated "no": it returns `null` and the caller decides
  (drop the event, log, count it). Reconstructing a plausible-looking signature instead turns a
  detectable parse failure into a value that flows downstream and only fails later, at verification,
  as a confusing "invalid signature".

## Decision

`Signature::fromHex` requires exactly 128 lowercase hex characters (a full 64-byte signature) and
returns `null` for anything else — shorter, longer, upper-case, or non-hex. There is no
zero-padding and no other repair.

If interoperability with a short-signature producer is ever genuinely required, it belongs at a
higher layer that can try both `r`-stripped and `s`-stripped reconstructions and accept only the one
that *verifies* — never in `fromHex`, which must not invent signature bytes.

## Consequences

- A signature that is not a complete 64 bytes is rejected at the boundary as a returned `null`
  (an anticipated outcome per ADR-0003), so the failure is visible where the input is first parsed.
- Events from producers that strip leading zero bytes from the signature fail to parse and are
  rejected. This is the accepted cost of conformance and of never fabricating signature bytes; it
  matches what every reference secp256k1 library already does.
- Do not add left-zero-padding to `fromHex` to "accept slightly short signatures" — it can
  reconstruct a wrong signature from an `s`-stripped input, and the safe place to handle
  non-conformant producers is a verify-and-pick step above the value object.
