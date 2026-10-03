# 112. A decoded JSON object is kept apart from a JSON array

## Status

Accepted

## Context

`json_decode($json, true)` reads a JSON object as a PHP array, and a PHP array is a list exactly when its keys run 0 to n-1. Two different JSON values therefore decode to the same PHP value: `{}` and `[]` both become `[]`, and an object keyed `"0"`, `"1"`, … becomes the list a JSON array of the same values would. A parser handed the decoded value cannot tell which the sender wrote.

Two wire positions turn on exactly that difference. NIP-01 writes a subscription as `["REQ", <subscription_id>, <filters1>, <filters2>, ...]` where each filter "is a JSON object", and `{}` is the filter that matches every event. Decoded the obvious way, `["REQ","s",[]]` — a JSON array where an object belongs — parsed as `["REQ","s",{}]` and opened a subscription to everything. A NIP-86 request's `params` is a list (nostr-adrs ADR-0037), and `{"params":{}}` or `{"params":{"0":"a"}}` parsed as one.

The encoder already makes the distinction in the other direction: an empty `Filter` serialises as `stdClass` so it is written `{}`, never `[]`.

## Decision

- `JsonWireFormat::decode(json)` decodes with JSON objects as `stdClass` and converts each one to a PHP array unless the array would be a list — the empty object, or one keyed 0 to n-1 — in which case it stays a `stdClass`. Every list in the result was a JSON array; every non-list array and every `stdClass` was a JSON object. Malformed JSON is `null`.
- JSON holding an object key that starts with U+0000 is malformed, and `decode` returns `null` for the whole input wherever the object sits. PHP cannot hold such a key as a `stdClass` property, so `json_decode` refuses the document; both cores read it the same way, and a key holding U+0000 after its first character is read as written.
- `decode` is the only JSON decoder. Each caller checks the shape its position takes: a list where NIP-01 writes a JSON array, a non-list array where it writes an object. Every message parsed from JSON carries the distinction to its leaf.
- A `REQ` or `COUNT` refuses a filter position holding the empty list; the match-everything filter is `{}`, which arrives as a `stdClass`. `Filter::tryFromJson` refuses `[]` and any other JSON array.
- `Nip86Request::tryFromJson` reads through `decode`, so `params` given as any JSON object is refused.
- An event read from JSON reads through `decode` wherever it arrives: `Event::tryFromJson`, the NIP-98 `Authorization` header, a NIP-59 seal and the rumour inside it, and a repost's embedded event. A `tags` written as `{}` or `{"0":[…]}` is refused there exactly as it is inside `["EVENT", …]`.
- A typed collection read from a wire position (`TagCollection`, `FilterCollection`, `EventKindCollection`, `EventIdCollection` and `PublicKeyCollection` `::tryFromArray`) takes only a list. A non-list array is a JSON object with keys that are not 0 to n-1, so `{"kinds":{"x":1}}` and an event whose `tags` is `{"x":["t","nostr"]}` are refused, as their `{"0":…}` spellings are.
- Kind-0 content (NIP-01: "`content` is set to a stringified JSON object"), a NIP-86 response and a NIP-61 proof read through `decode` and are refused when written as a JSON array. `{}` is a profile stating nothing; a NIP-86 `result` of `{}` re-serialises as `{}`, not `[]`.

## Consequences

- `Filter::tryFromArray([])` is still the empty filter: a PHP caller building a filter from its own array has no JSON to misread, and `Subscription::tryFromArray` restores what a host persisted. Only the wire positions, where the bytes are someone else's, are strict.
- A PHP caller building `["REQ", id, []]` by hand is refused; it passes `new stdClass()` or a `Filter`, which is what `ReqMessage::toArray()` produces.
- Do not "simplify" `decode` back to `json_decode($json, true)`: the round trip through `stdClass` is the only place the difference survives decoding.
- Shared decision: nostr-adrs ADR-0092.
