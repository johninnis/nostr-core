# 64. A COUNT reply carries an `EventCount`, not loose fields

## Status

Accepted

## Context

NIP-45 lets a relay answer a COUNT with an approximate figure, provided it says so: the reply payload is `{"count": N}` or `{"count": N, "approximate": true}`. The relay-side `CountMessage` modelled that payload as two loose constructor arguments, an `int` and an optional `?bool`, and exposed them as two accessors. A count and whether it is approximate are not two facts, though; they are one value, because the number means something different depending on the flag. Holding them apart lets a caller read the number and forget the flag, and lets a store or a relay produce a capped count without any type asking whether it was capped.

The concept also has a second owner. A relay library that lets a store bound the cost of a COUNT needs the store to say which kind of answer it gave, and the natural value for that is exactly this one. Defining it in the relay library would leave core with the two loose fields and the relay library with a value that the message then has to be unpacked into, two representations of one protocol payload.

Folding the value into `CountMessage` itself is the other tempting shape, and it is wrong. The message is the wire envelope: a subscription id, a message type and a serialisation. The count is the answer to a question the envelope carries. A store that counts events does not know which subscription asked, so it cannot return a message, and a client library reading a reply wants the answer without the envelope. They are two value objects because they have two lifetimes and two producers.

## Decision

`EventCount` is a protocol value object beside `SubscriptionId` and `Filter`: a non-negative count built through `EventCount::exact()` or `EventCount::approximate()`, read through `toInt()` and `isApproximate()`. `CountMessage` is constructed from a `SubscriptionId` and an `EventCount` and exposes the count as that value; it derives the wire payload from it, writing `approximate: true` only for an approximate count, and parsing an absent or `false` flag as exact.

## Consequences

- A relay that caps a count must build an approximate `EventCount`, and the reply then says so on the wire without a second step. The flag cannot be forgotten between the store and the socket.
- A client parsing a COUNT reply reads one value and branches on it. There is no separate nullable accessor to consult.
- The constructor changes shape and `getApproximate()` is gone, which is a breaking change on the 0.x line. The parsed form of an explicit `"approximate": false` is an exact count, so a reply that spelled the flag out serialises back without it; the two are the same statement.
- Do not add a `getApproximate(): ?bool` back for convenience, and do not let `CountMessage` accept a bare integer again. Both reopen the gap this record closes.
