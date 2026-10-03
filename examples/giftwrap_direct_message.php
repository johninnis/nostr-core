<?php

declare(strict_types=1);

use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Failure\GiftWrapUnwrapFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
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

$rumour = new RumourFactory($sender->getPublicKey())->createPrivateMessage(
    new PublicKeyCollection([$recipient->getPublicKey()]),
    EventContent::fromString('This message is sealed and gift-wrapped.'),
);

$giftWraps = $giftWrapper->wrapForChatRoom($rumour, $sender->getPrivateKey());
$giftWrap = array_find(
    $giftWraps->toArray(),
    static fn (Event $wrap): bool => $wrap->getTags()->getSoleValueByType(TagType::pubkey())->getValue() === $recipient->getPublicKey()->toHex(),
) ?? throw new RuntimeException('No gift wrap addressed to the recipient');
$unwrapped = $giftWrapper->unwrap($giftWrap, $recipient->getPrivateKey());

if ($unwrapped instanceof GiftWrapUnwrapFailure) {
    throw new RuntimeException('Unwrap failed: '.$unwrapped->message());
}

echo 'Signing backend:  '.$signer->backend()->name.' (a server-side signer should require Native; see SECURITY.md)'.PHP_EOL;
echo 'ECDH backend:     '.$ecdh->backend()->name.PHP_EOL;
echo 'Gift wraps:       '.count($giftWraps).' (the recipient and the sender)'.PHP_EOL;
echo 'Gift wrap kind:   '.$giftWrap->getKind()->toInt().PHP_EOL;
echo 'Gift wrap pubkey: '.$giftWrap->getPubkey()->toHex().' (ephemeral)'.PHP_EOL;
echo 'Unwrapped sender: '.$unwrapped->getPubkey()->toHex().PHP_EOL;
echo 'Message:          '.(string) $unwrapped->getContent().PHP_EOL;
