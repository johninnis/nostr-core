<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

final class MimeTypeParser
{
    private const string TYPE_AND_SUBTYPE = '~\A[a-z0-9][a-z0-9!#$&^_.+-]{0,126}/[a-z0-9][a-z0-9!#$&^_.+-]{0,126}\z~i';

    private function __construct()
    {
    }

    public static function tryParse(string $value): ?string
    {
        return 1 === preg_match(self::TYPE_AND_SUBTYPE, $value) ? strtolower($value) : null;
    }
}
