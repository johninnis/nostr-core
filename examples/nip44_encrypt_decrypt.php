<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\Service\ConversationCipher;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();
$cipher = new ConversationCipher(new Nip44Cipher(), Secp256k1Ecdh::create());

$sender = KeyPair::generate($signer);
$recipient = KeyPair::generate($signer);

$ciphertext = $cipher->encrypt('Meet me at the usual place.', $sender->getPrivateKey(), $recipient->getPublicKey());
$plaintext = $cipher->decrypt($ciphertext, $recipient->getPrivateKey(), $sender->getPublicKey());

echo 'Ciphertext: '.$ciphertext.PHP_EOL;
echo 'Decrypted:  '.$plaintext.PHP_EOL;
