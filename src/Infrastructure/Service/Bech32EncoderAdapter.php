<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;

final class Bech32EncoderAdapter implements Bech32EncoderInterface
{
    public function decodeComplexEntity(string $bech32): array
    {
        $decoded = Bech32Codec::decodeToTlv($bech32);

        return match ($decoded['type']) {
            'npub', 'nsec' => [
                'type' => 'pubkey',
                'pubkey' => $decoded['data'],
            ],
            'note' => [
                'type' => 'event',
                'event_id' => $decoded['data'],
            ],
            'profile' => [
                'type' => 'profile',
                'pubkey' => $decoded['pubkey'] ?? '',
                'relays' => $decoded['relays'] ?? [],
            ],
            'event' => [
                'type' => 'event',
                'event_id' => $decoded['event_id'] ?? '',
                'relays' => $decoded['relays'] ?? [],
                'author' => $decoded['author'] ?? null,
                'kind' => $decoded['kind'] ?? null,
            ],
            'address' => [
                'type' => 'address',
                'identifier' => $decoded['identifier'] ?? '',
                'pubkey' => $decoded['pubkey'] ?? '',
                'kind' => $decoded['kind'] ?? null,
                'relays' => $decoded['relays'] ?? [],
            ],
            default => throw new InvalidArgumentException("Unknown bech32 type: {$decoded['type']}"),
        };
    }

    public function encodeAddressableEvent(string $identifier, PublicKey $pubkey, int $kind, array $relays = []): string
    {
        return Bech32Codec::encodeNaddr($identifier, $pubkey->toHex(), $kind, $relays);
    }

    public function parseEventReference(string $input): EventId|EventCoordinate|null
    {
        if (str_starts_with($input, 'naddr1')) {
            $decoded = $this->decodeComplexEntity($input);

            if (!isset($decoded['kind'], $decoded['pubkey'], $decoded['identifier'])) {
                return null;
            }

            return EventCoordinate::fromParts(
                $decoded['kind'],
                $decoded['pubkey'],
                $decoded['identifier'],
                $decoded['relays'][0] ?? null
            );
        }

        if (str_starts_with($input, 'note1')) {
            $decoded = $this->decodeComplexEntity($input);

            return isset($decoded['event_id']) ? EventId::fromHex($decoded['event_id']) : null;
        }

        if (str_starts_with($input, 'nevent1')) {
            $decoded = $this->decodeComplexEntity($input);

            return isset($decoded['event_id']) ? EventId::fromHex($decoded['event_id']) : null;
        }

        return EventId::fromHex($input);
    }
}
