# 22. `Event::fromArray` coerces non-string `content` to its JSON string rather than rejecting it

## Status

Accepted

## Context

NIP-01 says an event's `content` is a string. `Event::build` parses untrusted wire data, and every other field follows one rule: a type mismatch returns `null` and the whole event is rejected. `content` is the single exception — when it arrives as a non-string (a JSON object, number, boolean, or null, which `json_decode(..., true)` yields as a PHP array or scalar), `build` does not reject the event. It re-encodes the value back to a JSON string and uses that as the content.

Side by side with the strict handling of every other field, this reads like an oversight: the obvious "correction" is to make `content` return `null` on a non-string like the rest, giving one uniform strict-parse rule.

Two forces argue against that correction:

- **Sloppy producers exist, and dropping the event loses more than keeping it.** A handful of clients emit a JSON object where a string belongs (most visibly kind-0 metadata). Rejecting the whole event discards data that non-cryptographic consumers — indexers, metadata readers, archival stores — can still use. Coercing to the JSON string preserves it in the one representation the rest of the system expects.
- **Coercion cannot manufacture a valid signature, so it is safe.** A correctly signed event is always signed over a *string* content; a non-string content was therefore never validly signed. The id is recomputed from `(string) $content`, so a coerced event whose wire data carries an `id`/`sig` simply fails `verify()` — coercion never turns malformed input into a false-positive verification. Its only effect is to keep the data readable on the paths that do not require verification.

## Decision

`Event::fromArray` coerces a non-string `content` to its JSON-encoded string form instead of rejecting the event. The coercion uses the same canonical event encoding the library applies to content everywhere else (`JsonWireFormat::EVENT`), so the resulting string — and any id recomputed from it — is in the library's one canonical form rather than a second, differently-escaped one. If the value cannot be encoded at all (for example, invalid UTF-8), the event is rejected with `null`.

This is the deliberate, single exception to the otherwise-strict "type mismatch returns null" rule in the parser, and it is confined to `content`.

## Consequences

- Events from producers that put a non-string in `content` survive parsing and stay usable by consumers that do not verify signatures.
- A coerced event still cannot pass `verify()` unless it was genuinely signed over that exact string, so the leniency never weakens the signature guarantee.
- The coercion shares the canonical event-encoding flags, so a coerced content and a recomputed id are byte-identical to how the library encodes any other event content — there is not a second escaping convention to reconcile.
- A test pins the coercion (`testFromArrayHandlesNonStringContent`). Do not "tighten" `content` to reject non-strings for consistency with the other fields — the leniency is deliberate, and the safety argument above is why it does not compromise verification.
