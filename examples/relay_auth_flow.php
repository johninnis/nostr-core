<?php

declare(strict_types=1);

use Innis\Nostr\Core\Application\Service\Nip42Validator;
use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Innis\Nostr\Core\Infrastructure\Time\SystemClock;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();
$relayUrl = RelayUrl::tryFromString('wss://relay.example.com') ?? throw new RuntimeException('Invalid relay URL');

$challenge = Challenge::fromString(bin2hex(random_bytes(16)));

echo 'Relay sends: '.new AuthMessage($challenge)->toJson().PHP_EOL;
echo '             a Challenge cannot hold the empty string, so an unknown session cannot produce one by accident'.PHP_EOL;
echo PHP_EOL;

$client = KeyPair::generate($signer);
$reply = RumourFactory::createAuth($client->getPublicKey(), $relayUrl, $challenge)->sign($client, $signer);

$validator = new Nip42Validator(new SystemClock());
$failure = $validator->validate($reply, $challenge, $relayUrl);

echo 'Client replies with a kind '.$reply->getKind()->toInt().' event naming the challenge and this relay'.PHP_EOL;
echo 'Accepted:    '.(null === $failure ? 'yes' : 'no').PHP_EOL;
echo '             the signature is not checked here; an AUTH event is validated as an event first'.PHP_EOL;
echo PHP_EOL;

$staleFailure = $validator->validate($reply, Challenge::fromString('a-challenge-this-relay-never-issued'), $relayUrl)
    ?? throw new RuntimeException('an answer to another challenge must never validate');

echo 'Replayed against another challenge: '.$staleFailure->value.PHP_EOL;
echo 'Wire reason:                        '.ReasonPrefix::AuthRequired->format($staleFailure->message()).PHP_EOL;
