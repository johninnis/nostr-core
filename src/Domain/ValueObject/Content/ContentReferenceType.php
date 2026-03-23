<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

enum ContentReferenceType: string
{
    case NostrUri = 'nostr_uri';
    case BareNpub = 'bare_npub';
    case BareNote = 'bare_note';
    case BareNevent = 'bare_nevent';
    case BareNprofile = 'bare_nprofile';
    case BareNaddr = 'bare_naddr';
    case LegacyRef = 'legacy_ref';
}
