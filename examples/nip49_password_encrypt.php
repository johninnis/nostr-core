<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\Enum\KeySecurityByte;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Ncryptsec;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PrivateKey;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip49Cipher;

require __DIR__.'/../vendor/autoload.php';

$cipher = Nip49Cipher::create();
$privateKey = PrivateKey::generate();
$password = static fn (): string => 'correct horse battery staple';

$ncryptsec = $cipher->encrypt($privateKey, $password, logN: 16, keySecurity: KeySecurityByte::ClientSideOnly);
$stored = (string) $ncryptsec;

$decoded = Ncryptsec::fromString($stored) ?? throw new RuntimeException('Failed to parse ncryptsec');
$recovered = $cipher->decrypt($decoded, $password);

echo 'ncryptsec:      '.$stored.PHP_EOL;
echo 'Original key:   '.$privateKey->toHex().PHP_EOL;
echo 'Recovered key:  '.$recovered->toHex().PHP_EOL;
echo 'Round-trip OK:  '.($privateKey->toHex() === $recovered->toHex() ? 'yes' : 'no').PHP_EOL;
