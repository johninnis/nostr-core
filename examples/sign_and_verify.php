<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();

$keyPair = KeyPair::generate($signer);

$note = EventFactory::createTextNote(
    $keyPair->getPublicKey(),
    'Hello from innis/nostr-core',
);

$signed = $note->sign($keyPair, $signer);
$event = $signed->toArray();

echo 'Event id:  '.$signed->getId()->toHex().PHP_EOL;
echo 'Pubkey:    '.$signed->getPubkey()->toHex().PHP_EOL;
echo 'Verified:  '.($signed->verify($signer) ? 'yes' : 'no').PHP_EOL;
echo PHP_EOL;
echo json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
