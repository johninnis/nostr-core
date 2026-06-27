<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Factory\EventFactory;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Infrastructure\Crypto\GiftWrapper;
use Innis\Nostr\Core\Infrastructure\Crypto\Nip44Cipher;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Ecdh;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();
$ecdh = Secp256k1Ecdh::create();
$giftWrapper = GiftWrapper::create(new Nip44Cipher(), $signer, $ecdh);

$sender = KeyPair::generate($signer);
$recipient = KeyPair::generate($signer);

$rumour = EventFactory::createRumour(
    $sender->getPublicKey(),
    'This message is sealed and gift-wrapped.',
    new TagCollection([Tag::pubkey($recipient->getPublicKey()->toHex())]),
);

$giftWrap = $giftWrapper->wrapForRecipient($rumour, $sender->getPrivateKey(), $recipient->getPublicKey());
$unwrapped = $giftWrapper->unwrap($giftWrap, $recipient->getPrivateKey());

echo 'Gift wrap kind:   '.$giftWrap->getKind()->toInt().PHP_EOL;
echo 'Gift wrap pubkey: '.$giftWrap->getPubkey()->toHex().' (ephemeral)'.PHP_EOL;
echo 'Unwrapped sender: '.$unwrapped->getPubkey()->toHex().PHP_EOL;
echo 'Message:          '.(string) $unwrapped->getContent().PHP_EOL;
