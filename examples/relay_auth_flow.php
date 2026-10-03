<?php

declare(strict_types=1);

use Innis\Nostr\Core\Application\Service\Nip42Validator;
use Innis\Nostr\Core\Domain\Enum\ReasonPrefix;
use Innis\Nostr\Core\Domain\Factory\RumourFactory;
use Innis\Nostr\Core\Domain\Service\EventValidator;
use Innis\Nostr\Core\Domain\Service\Nip42EventChecker;
use Innis\Nostr\Core\Domain\Service\NipComplianceValidator;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Challenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay\AuthMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayChallenge;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Innis\Nostr\Core\Infrastructure\Time\SystemClock;

require __DIR__.'/../vendor/autoload.php';

$signer = Secp256k1Signer::create();
$relayUrl = RelayUrl::fromString('wss://relay.example.com');

$challenge = Challenge::fromString(bin2hex(random_bytes(16)));
$relayChallenge = new RelayChallenge($relayUrl, $challenge);

echo 'Relay sends: '.new AuthMessage($challenge)->toJson().PHP_EOL;
echo '             a Challenge cannot hold the empty string, so an unknown session cannot produce one by accident'.PHP_EOL;
echo PHP_EOL;

$client = KeyPair::generate($signer);
$reply = new RumourFactory($client->getPublicKey())->createAuth($relayChallenge)->sign($client, $signer);

$clock = new SystemClock();
$eventValidator = new EventValidator($signer, new NipComplianceValidator($signer));
$validator = new Nip42Validator(new Nip42EventChecker(), $clock);

$isValidEvent = $eventValidator->isEventValid($reply, $clock->now());
$failure = $validator->validate($reply, $relayChallenge);

echo 'Client replies with a kind '.$reply->getKind()->toInt().' event naming the challenge and this relay'.PHP_EOL;
echo 'Valid event: '.($isValidEvent ? 'yes' : 'no').PHP_EOL;
echo 'Accepted:    '.($isValidEvent && null === $failure ? 'yes' : 'no').PHP_EOL;
echo '             the event, signature included, is validated first, then the AUTH answer against the challenge'.PHP_EOL;
echo PHP_EOL;

$staleFailure = $validator->validate($reply, new RelayChallenge($relayUrl, Challenge::fromString('a-challenge-this-relay-never-issued')))
    ?? throw new RuntimeException('an answer to another challenge must never validate');

echo 'Replayed against another challenge: '.$staleFailure->value.PHP_EOL;
echo 'Wire reason:                        '.ReasonPrefix::AuthRequired->format($staleFailure->message()).PHP_EOL;
