# 40. `ZapAmount::fromBolt11` anchors the amount to the bech32 separator

## Status

Accepted

## Context

A BOLT-11 invoice's human-readable part is `ln` + a currency prefix + an *optional* amount, followed
by the bech32 separator `1` and the data part. The amount, when present, is a run of digits and an
optional multiplier (`m`/`u`/`n`/`p`); when absent the invoice carries no amount at all and the payer
chooses it.

The obvious extraction — `/^ln[a-z]+?(\d+)([munp])?/i` — is wrong twice over. On an amount-less invoice
such as `lnbc1...` it captures the bech32 *separator* `1` as the amount and, finding no multiplier,
falls through to the whole-BTC default, reading an invoice that encodes no amount as **one bitcoin**.
And the `/i` flag matches upper-case `M`/`U`/`N`/`P`, none of which the multiplier table recognises, so
an upper-cased invoice also falls through to whole-BTC.

This is a value parser of untrusted input. A parser that returns a plausible-but-wrong value is worse
than one that returns nothing: a caller cannot tell the fabricated bitcoin from a real one. (The zap
flow happens to cross-check `bolt11` against the request's `amount` tag, but `fromBolt11` is public and
must be correct on its own.)

## Decision

The pattern is `/^ln[a-z]+?(\d+)([munp])?1/`, applied to the lower-cased input.

- The trailing `1` anchors the captured digits to the bech32 separator, so only a real amount — the
  digit run that immediately precedes the separator — is captured. An amount-less invoice has no digits
  before its separator, so the match fails and the parser returns `null`.
- The input is lower-cased once up front (bech32 is case-insensitive), and the pattern is then
  case-sensitive, so the multiplier letters keep their defined meaning instead of silently degrading an
  upper-cased multiplier to the whole-BTC default.

## Consequences

- `fromBolt11` returns `null` for an amount-less invoice rather than fabricating 1 BTC, and parses
  upper-cased invoices correctly.
- A whole-BTC amount (digits with no multiplier) is still parsed: its digit run sits immediately before
  the separator, e.g. `lnbc11...` is one bitcoin.
- The lone `1` at the end of the pattern reads like a typo. Do not "simplify" it away: it is the
  separator anchor, and without it an amount-less invoice parses as one bitcoin. A test pins the
  amount-less and whole-BTC cases.
