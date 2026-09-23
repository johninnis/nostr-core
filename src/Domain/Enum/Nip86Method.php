<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum Nip86Method: string
{
    case SupportedMethods = 'supportedmethods';
    case BanPubkey = 'banpubkey';
    case UnbanPubkey = 'unbanpubkey';
    case ListBannedPubkeys = 'listbannedpubkeys';
    case AllowPubkey = 'allowpubkey';
    case UnallowPubkey = 'unallowpubkey';
    case ListAllowedPubkeys = 'listallowedpubkeys';
    case CreateRole = 'createrole';
    case EditRole = 'editrole';
    case DeleteRole = 'deleterole';
    case AssignRole = 'assignrole';
    case UnassignRole = 'unassignrole';
    case ListEventsNeedingModeration = 'listeventsneedingmoderation';
    case AllowEvent = 'allowevent';
    case BanEvent = 'banevent';
    case ListBannedEvents = 'listbannedevents';
    case ChangeRelayName = 'changerelayname';
    case ChangeRelayDescription = 'changerelaydescription';
    case ChangeRelayIcon = 'changerelayicon';
    case AllowKind = 'allowkind';
    case DisallowKind = 'disallowkind';
    case ListAllowedKinds = 'listallowedkinds';
    case BlockIp = 'blockip';
    case UnblockIp = 'unblockip';
    case ListBlockedIps = 'listblockedips';
}
