# 83. `ZapAmount::tryFromBolt11` reads only the BOLT-11 bitcoin prefixes, anchored to the bech32 separator

## Status

Accepted

Supersedes ADR-0040. The separator anchor, the lower-casing and the pico rule are carried forward unchanged and are now the shared decision, nostr-adrs ADR-0039. The currency prefix is revised: it is no longer any run of letters. A mixed-case invoice is refused, and so is an amount of zero or one with a leading zero. This record holds what is particular to this package.

## Context

nostr-adrs ADR-0039 decides how every implementation reads a BOLT-11 amount: only the four bitcoin prefixes BOLT-11 names, mixed case refused, the amount anchored to the bech32 separator so an amount-less invoice is not one bitcoin, an amount of zero or with a leading zero refused, a `p` amount ending in a non-zero digit refused, and nothing above one bitcoin.

BOLT-11 tells a writer to "encode `amount` as a positive decimal integer with no leading 0s" and that "If the `p` multiplier is used the last decimal of `amount` MUST be `0`"; a reader "MUST fail the payment" for a `p` amount whose last decimal is not `0`.

ADR-0040's pattern accepted `ln[a-z]+?`, so an invoice for any currency, or a made-up one, was read as a bitcoin amount, and it read a mixed-case invoice that BIP-173 says "Decoders MUST NOT accept".

## Decision

`ZapAmount::tryFromBolt11` implements nostr-adrs ADR-0039. After an invoice that is neither all lower case nor all upper case has been refused, it lower-cases the invoice, takes the human-readable part up to the last `1`, and matches the whole of it against `/^ln(?:bcrt|bc|tbs|tb)([1-9]\d*)([munp])?$/D`.

- The last `1` is the bech32 separator: BIP-173 says the separator is always `1` and, "In case "1" is allowed inside the human-readable part, the last one in the string is the separator", and no data character is `1`. Matching the whole human-readable part ends the amount at that separator; a pattern that stopped at the first `1` read `lnbc10u1xyz1qqq` as 10 µBTC and `lnbc1m11qq` as 1 mBTC, although neither human-readable part is a prefix and an amount.
- The amount is a positive decimal integer with no leading `0`: `lnbc0u…`, `lnbc010u…` and `lnbc01…` are `null`, never zero or the value their digits would give once the zero is stripped.
- A `p` amount whose last digit is not `0` is `null`.
- A `p` amount is divided by ten to millisatoshis, which is exact once its last digit is `0`.
- `ZapAmount::MAX_MILLISATS` is one bitcoin; an amount above it is `null`, checked before multiplying so it cannot overflow.
- Every refusal is `null`, never a throw.

## Consequences

- Tests pin the spec's example invoices, its mixed-case vector, the amount-less case, the unknown prefixes, zero and leading-zero amounts, and the spec's invalid sub-millisatoshi invoice.
- Adding a prefix follows BOLT-11, not what one wallet emits, and lands in the shared record first.
- Do not strip a leading `0` or read a zero amount to be forgiving.
- Do not match the amount from the start of the invoice up to the first `1`, nor drop the anchor to the separator: the amount is the whole human-readable part after the prefix.
- Shared decision: nostr-adrs ADR-0039.
