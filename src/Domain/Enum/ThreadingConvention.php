<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;

enum ThreadingConvention
{
    case ShortNote;
    case Comment;
    case Channel;
    case LiveChat;
    case Chat;

    // Deliberate: the one place a kind's threading is decided, so the reply chain, its analyser and the reply builder cannot disagree on which kinds thread or how, and naming no kind reads the tags as a NIP-10 thread — see ADR-0085
    public static function ofKind(?EventKind $kind): ?self
    {
        return match ($kind?->toInt()) {
            null, EventKind::TEXT_NOTE => self::ShortNote,
            EventKind::COMMENT, EventKind::VOICE_MESSAGE_COMMENT => self::Comment,
            EventKind::CHANNEL_MESSAGE => self::Channel,
            EventKind::LIVE_CHAT_MESSAGE => self::LiveChat,
            EventKind::CHAT_MESSAGE => self::Chat,
            default => null,
        };
    }

    // Deliberate: a channel message's root is its channel, so a root alone answers no message — see nostr-adrs ADR-0012
    public function answersItsRoot(): bool
    {
        return self::Channel !== $this;
    }
}
