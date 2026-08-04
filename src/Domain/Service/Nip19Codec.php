<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Naddr;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nevent;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nip19EntityInterface;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Note;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Nprofile;
use Innis\Nostr\Core\Domain\ValueObject\Nip19\Npub;
use Override;

final readonly class Nip19Codec implements Nip19CodecInterface
{
    // Deliberate: resolves an entity of unknown prefix; a caller that already knows the type calls that leaf's tryFromBech32 instead — see ADR-0060
    #[Override]
    public function decodeComplexEntity(string $bech32): ?Nip19EntityInterface
    {
        $decoded = Bech32Codec::decode($bech32);

        if (null === $decoded) {
            return null;
        }

        $payload = $decoded['data'];

        return match ($decoded['hrp']) {
            PublicKey::BECH32_HRP => self::decodeNpub($payload),
            EventId::BECH32_HRP => self::decodeNote($payload),
            Nprofile::HRP => Nprofile::tryFromPayload($payload),
            Nevent::HRP => Nevent::tryFromPayload($payload),
            Naddr::HRP => Naddr::tryFromPayload($payload),
            default => null,
        };
    }

    #[Override]
    public function parseEventReference(string $input): EventId|EventCoordinate|null
    {
        $entity = $this->decodeComplexEntity($input);

        return match (true) {
            $entity instanceof Note => $entity->getEventId(),
            $entity instanceof Nevent => $entity->getEventId(),
            $entity instanceof Naddr => $entity->getCoordinate()->withRelayHint($entity->getRelays()->toArray()[0] ?? null),
            default => EventId::tryFromHex($input),
        };
    }

    private static function decodeNpub(string $payload): ?Npub
    {
        $publicKey = PublicKey::tryFromBytes($payload);

        return null === $publicKey ? null : Npub::fromPublicKey($publicKey);
    }

    private static function decodeNote(string $payload): ?Note
    {
        $eventId = EventId::tryFromBytes($payload);

        return null === $eventId ? null : Note::fromEventId($eventId);
    }
}
