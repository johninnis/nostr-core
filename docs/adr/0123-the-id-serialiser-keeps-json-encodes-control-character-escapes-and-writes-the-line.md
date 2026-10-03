# 123. The id serialiser keeps `json_encode`'s control-character escapes and writes the line terminators verbatim

## Status

Accepted

## Context

`Rumour::getId` hashes the NIP-01 array as `JsonWireFormat::encode` writes it under `JsonWireFormat::EVENT`, and `Event` keeps the id it was signed with (ADR-0046), so the id of every event this package builds or checks rests on those flags. nostr-adrs ADR-0105 decides, for every implementation, that the id is computed over the array as a JSON encoder writes it, a control character NIP-01 does not name escaped as `\u00XX`, and everything else verbatim, as a deliberate departure from NIP-01's "all other characters must be included verbatim".

`json_encode` gives that form only under the right flags, and two of them look like tidying candidates:

- Without `JSON_UNESCAPED_UNICODE`, every character outside ASCII is written as a `\uXXXX` escape. `FilterHasher` relies on that (ADR-0020), so a reader may take one flag set to be enough for both.
- Under `JSON_UNESCAPED_UNICODE` alone, `json_encode` still escapes U+2028 and U+2029 as ` ` and ` `, which JavaScript's `JSON.stringify` and every implementation in the ecosystem write verbatim. Only `JSON_UNESCAPED_LINE_TERMINATORS` stops it, and the flag is easy to read as a no-op left over from an older PHP.

Neither flag changes how a control character is written: `json_encode` writes the seven NIP-01 names with their short escapes and every other character from U+0000 to U+001F as `\u00XX` in lower-case hex under any flag set, which is the form ADR-0105 decides.

## Decision

- `JsonWireFormat::EVENT` is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS`, and it is the flag set an event's id is computed with.
- The control-character escapes are `json_encode`'s own. Nothing post-processes the encoded string, and nothing writes a control character raw to follow NIP-01's wording.
- `JsonWireFormat::FILTER_HASH` stays a separate flag set (ADR-0020); the two are not unified.

## Consequences

- An event holding U+0001 has the id nostr-tools gives it. A test pins the encoded form of U+0001 and U+001F, the seven short escapes, U+007F, U+2028 and U+2029 written verbatim, and the id of the shared vector: content `a` U+0001 `b`, pubkey `79be667ef9dcbbac55a06295ce870b07029bfcdb2dce28d959f2815b16f81798`, `created_at` 1700000000, kind 1, no tags, id `0f8048f56ac7b672dcce8e44bf37294ed15345c228c24afe07339ddf85772a22`.
- Do not drop `JSON_UNESCAPED_LINE_TERMINATORS` or `JSON_UNESCAPED_UNICODE` from `EVENT`, do not encode an id with `MESSAGE` or `FILTER_HASH`, and do not replace `json_encode` with a hand-written serialiser that follows NIP-01 to the letter.
- Shared decision: nostr-adrs ADR-0105.
