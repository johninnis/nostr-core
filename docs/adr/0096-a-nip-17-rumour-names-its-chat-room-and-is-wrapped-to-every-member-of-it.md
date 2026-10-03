# 96. A NIP-17 rumour names its chat room, and is wrapped to every member of it

## Status

Accepted

## Context

nostr-adrs ADR-0074 decides for every implementation that a rumour's chat room is its author and every pubkey its `p` tags name; that a private message and a private reaction are built from the room's other members, each tagged once, a reaction tagging its target's author last even when that is the sender; that neither builder reads its room from the message it answers; and that a room's rumour is gift-wrapped to every member, the sender included.

`GiftWrapper` here once wrapped a rumour to one recipient at a time, and nothing built a message or a reaction for a room, so every caller tagged the receivers, wrapped once per receiver, and had to remember its own copy.

## Decision

This package implements nostr-adrs ADR-0074 as follows:

- `Rumour::getChatRoom()` is the author and every pubkey its `p` tags name, once each and sorted, so two rumours are in one room exactly when their rooms are equal.
- `RumourFactory::createPrivateMessage(receivers, content, ?replyTo)` and `createPrivateReaction(receivers, message, ?reaction)`, on a factory built for the sender (ADR-0105), take the room's members as a `PublicKeyCollection`. The factory's author is left out of the receivers, since the author is a member by being the author, and each other member is tagged once. When no member other than the author remains, the room is the author alone, a room of one, and its one `p` tag names the author: NIP-17's room is "the set of `pubkey` + `p` tags", so the tag states the room the author already is.
- A message answering another names it with an `e` tag; a reaction carries `["e", <id>, "", <author>]` and `["k", <kind>]`.
- A reaction always `p`-tags the author of the message it answers, last among its `p` tags, as NIP-25 asks ("the target event `pubkey` should be last the `p` tags"), and does so even when that author is the sender. The other members are tagged first, each once, and the message's author is not tagged twice. Since the sender is a member of the room by being the author, tagging it adds no member: the reaction's room is still the message's room, and `wrapForChatRoom` still wraps it once per member. A message whose author is neither the sender nor among the receivers is refused with `InvalidArgumentException`, since its `p` tag would move the reaction to another room.
- Each factory takes at most three arguments, in @innis/nostr-core's order: the receivers, the payload or the message answered, and the optional third. The receivers and the message answered are independent inputs — a reaction's room is named by its receivers and not read from its message — so they are not bundled into one value.
- `GiftWrapper::wrapForChatRoom(rumour, senderKey)` wraps the rumour to every member of its room, the sender included, once each; the room is read from the rumour, so what was addressed is what is wrapped, and a room of one is wrapped exactly once, to its author.
- `GiftWrapper::wrapForRecipient` is the separate, named per-recipient wrap a client calls for NIP-17's disappearing messages ("by not generating a gift wrap to the sender's public key").

## Consequences

- A caller wraps a private message or reaction with one call and cannot forget the sender's copy.
- Passing the sender among the receivers builds the same room as leaving it out; passing only the sender, or no one, builds a room of one.
- A reaction to the sender's own message carries a `p` tag naming the sender; do not drop it as redundant with the author, since NIP-25 places the target's author last and a reader looks there.
- Do not make the reaction read its room from its target, add a room-less overload, or give the room's wrap a list of recipients.
- Shared decisions: nostr-adrs ADR-0074 and ADR-0075.
