# 69. NIP-86 request and response are protocol values, with the method names

## Status

Accepted

## Context

NIP-86 defines a relay management API: a JSON body of `{"method": ..., "params": [...]}` posted with the `application/nostr+json+rpc` media type, answered with `{"result": ...}` or `{"error": ...}`, authenticated by a NIP-98 header, and a fixed list of method names a client may call. It is a two-peer protocol: a management client and a relay must agree on the media type, the envelope and the names.

This library owned the NIP-98 half of that transaction and nothing of the NIP-86 half. The one relay application implementing it typed the media type, parsed the envelope by hand and listed the spec's method names as string keys in its dispatch table, so a management client written against this library would have had to type all of it again and could drift from the relay on any of it.

## Decision

`Nip86Request` and `Nip86Response` are protocol value objects. A request carries a non-empty method name and a list of params, parses from JSON or an array with `tryFromArray()` and `tryFromJson()`, and serialises back; `Nip86Request::MEDIA_TYPE` is the media type. A response is either `success(mixed $result)` or `failure(string $error)`, serialises to exactly one of the two keys, and parses the same way. `Nip86Method` is a backed enum of the method names the specification defines, including `supportedmethods`.

The method on a request is a string, not the enum. A relay may serve methods the specification does not define, and a client may call them; the enum is for the spec's names, and `Nip86Method::tryFrom()` says whether a given name is one of them.

## Consequences

- A relay dispatches on the request's method name and keys its spec-defined handlers by `Nip86Method` cases, so a misspelled name is a type error rather than a silent 400. Its own extra methods stay plain strings.
- A client builds a request, posts it with the media type constant and a NIP-98 header, and reads the response as a value.
- A failure response serialises `error` alone and a success serialises `result` alone. Do not emit both to look uniform; a client branches on which key is present.
- The parser accepts a missing `params` as an empty list, because a method taking no parameters has nothing to put there, and refuses a non-list `params`.
- An `error` of the empty string is neither a failure nor a success, and the parser refuses the whole envelope rather than guessing which was meant. A relay that has nothing to say about a failure has not described one.
