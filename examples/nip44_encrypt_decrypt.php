<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\ValueObject\Identity\ConversationKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();
$ecdh = Secp256k1Ecdh::create();

$sender = KeyPair::generate($signer);
$recipient = KeyPair::generate($signer);

$senderToRecipient = ConversationKey::derive($sender->getPrivateKey(), $recipient->getPublicKey(), $ecdh);
$recipientToSender = ConversationKey::derive($recipient->getPrivateKey(), $sender->getPublicKey(), $ecdh);

$cipher = new Nip44Cipher();

$ciphertext = $cipher->encrypt('Meet me at the usual place.', $senderToRecipient);
$plaintext = $cipher->decrypt($ciphertext, $recipientToSender);

echo 'Ciphertext: '.$ciphertext.PHP_EOL;
echo 'Decrypted:  '.$plaintext.PHP_EOL;
