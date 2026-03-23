<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Fixtures;

final class TestKeys
{
    public const PRIVATE_KEY_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    public const PUBLIC_KEY_HEX = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';
    public const SIGNATURE_HEX = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public const NPUB_BECH32 = 'npub1lmkturrkv5p3qnhv43yzkmav49hmuuy3qgmuuy3qgmuuy3qgmuqyckyzz';
    public const NSEC_BECH32 = 'nsec1qy35v6y64hlhxqy35v6y64hlhxqy35v6y64hlhxqy35v6y64hms9v';
}
