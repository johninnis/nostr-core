<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventCoordinate;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use InvalidArgumentException;
use nostriphant\NIP19\Bech32;

final class Bech32EncoderAdapter implements Bech32EncoderInterface
{
    public function decodeComplexEntity(string $bech32): array
    {
        $decoded = new Bech32($bech32);
        $prefix = substr($bech32, 0, strpos($bech32, '1') ?: 0);

        switch ($prefix) {
            case 'npub':
                return [
                    'type' => 'pubkey',
                    'pubkey' => $decoded(),
                ];

            case 'note':
                return [
                    'type' => 'event',
                    'event_id' => $decoded(),
                ];

            case 'nprofile':
                $data = $decoded->data;

                return [
                    'type' => 'profile',
                    'pubkey' => $data->pubkey ?? '',
                    'relays' => $data->relays ?? [],
                ];

            case 'nevent':
                $data = $decoded->data;

                return [
                    'type' => 'event',
                    'event_id' => $data->id ?? '',
                    'relays' => $data->relays ?? [],
                    'author' => $data->author ?? null,
                    'kind' => $data->kind ?? null,
                ];

            case 'naddr':
                $data = $decoded->data;

                return [
                    'type' => 'address',
                    'identifier' => $data->identifier ?? '',
                    'pubkey' => $data->pubkey ?? '',
                    'kind' => $data->kind ?? null,
                    'relays' => $data->relays ?? [],
                ];

            default:
                throw new InvalidArgumentException("Unknown bech32 prefix: {$prefix}");
        }
    }

    public function encodeAddressableEvent(string $identifier, PublicKey $pubkey, int $kind, array $relays = []): string
    {
        return (string) Bech32::naddr(
            identifier: $identifier,
            pubkey: $pubkey->toHex(),
            kind: $kind,
            relays: $relays
        );
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
