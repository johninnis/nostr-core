# 69. NIP-86 request and response are protocol values, with the method names, and a response is read by its error

## Status

Accepted

## Context

NIP-86 defines a relay management API: a JSON body of `{"method": ..., "params": [...]}` posted with the `application/nostr+json+rpc` media type, answered with `{"result": ...}` or `{"error": ...}`, authenticated by a NIP-98 header, and a fixed list of method names a client may call. It is a two-peer protocol: a management client and a relay must agree on the media type, the envelope and the names.

NIP-86's response template shows both keys, `"result": {…}` beside `"error": "<optional error message, if the call has errored>"`, so a relay filling the template may send both, with an `error` that is absent, `null` or empty when the call succeeded. Refusing that response refuses a relay that answered the call.

## Decision

`Nip86Request` and `Nip86Response` are protocol value objects. A request carries a non-empty method name and a list of params, parses from JSON or an array with `tryFromArray()` and `tryFromJson()`, and serialises back; `Nip86Request::MEDIA_TYPE` is the media type. A response is either `success(mixed $result)` or `failure(string $error)`, serialises to exactly one of the two keys, and parses the same way. `Nip86Method` is a backed enum of the method names the specification defines, including `supportedmethods`.

- The method on a request is a string, not the enum. A relay may serve methods the specification does not define, and a client may call them; the enum is for the spec's names, and `Nip86Method::tryFrom()` says whether a given name is one of them.
- A request without `params` has an empty list of them; a `params` that is not a JSON list, an object included, refuses the request (ADR-0092).
- A response is read by its `error`: a non-empty string `error` is a failure, whether or not a `result` stands beside it. An `error` that is absent, `null` or the empty string describes no failure, and the response is a success when it carries `result`.
- A response with no `result` and no non-empty `error`, or with an `error` that is neither a string nor `null`, is refused rather than guessed at.
- A response is written with one outcome: `result` alone for a success, a non-empty `error` alone for a failure, so an empty `error` read beside a `result` is written back as the `result` alone.

## Consequences

- A relay dispatches on the request's method name and keys its spec-defined handlers by `Nip86Method` cases, so a misspelled name is a type error rather than a silent 400. Its own extra methods stay plain strings.
- A client builds a request, posts it with the media type constant and a NIP-98 header, and reads the response as a value; a relay that fills the template with an empty or null `error` beside its `result` is understood.
- A relay with nothing to say about a failure has not described one: `failure('')` throws, and an envelope stating only an empty `error` is refused.
- Do not emit both keys to look uniform: reading leniently is for other relays, and writing strictly is for other readers.
- Shared decision: nostr-adrs ADR-0037.
