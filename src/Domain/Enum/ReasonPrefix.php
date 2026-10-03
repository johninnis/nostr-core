<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Enum;

enum ReasonPrefix: string
{
    case Duplicate = 'duplicate';
    case Pow = 'pow';
    case Blocked = 'blocked';
    case RateLimited = 'rate-limited';
    case Invalid = 'invalid';
    case Restricted = 'restricted';
    case Mute = 'mute';
    case Error = 'error';
    case AuthRequired = 'auth-required';

    public function format(string $detail): string
    {
        return $this->value.': '.$detail;
    }

    public static function tryFromMessage(string $message): ?self
    {
        $separator = strpos($message, ':');

        return false === $separator ? null : self::tryFrom(substr($message, 0, $separator));
    }

    // Deliberate: NIP-01 requires every refusal to carry a prefix and names error "for when none of that fits", so a refusal naming no known prefix is an error — see ADR-0087
    public static function ofRefusal(string $message): self
    {
        return self::tryFromMessage($message) ?? self::Error;
    }
}
