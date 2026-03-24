<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Service;

use Exception;
use Innis\Nostr\Core\Domain\Entity\ContentReference;
use Innis\Nostr\Core\Domain\Enum\ContentReferenceType;
use Innis\Nostr\Core\Domain\Service\Bech32EncoderInterface;
use Innis\Nostr\Core\Domain\Service\ContentReferenceExtractorInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;

final class ContentReferenceExtractorAdapter implements ContentReferenceExtractorInterface
{
    public function __construct(
        private Bech32EncoderInterface $bech32Encoder,
    ) {
    }

    public function extractContentReferences(EventContent $content): array
    {
        $references = [];
        $usedPositions = [];

        $contentString = (string) $content;

        $patterns = [
            ContentReferenceType::NostrUri->value => '/nostr:(npub1[a-z0-9]{58}|nprofile1[a-z0-9]+|note1[a-z0-9]{58}|nevent1[a-z0-9]+|naddr1[a-z0-9]+)/i',
            ContentReferenceType::BareNpub->value => '/(?<![a-z0-9])npub1[a-z0-9]{58}(?=nostr:|[^a-z0-9]|$)/i',
            ContentReferenceType::BareNote->value => '/(?<![a-z0-9])note1[a-z0-9]{58}(?=nostr:|[^a-z0-9]|$)/i',
            ContentReferenceType::BareNevent->value => '/(?<![a-z0-9])nevent1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i',
            ContentReferenceType::BareNprofile->value => '/(?<![a-z0-9])nprofile1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i',
            ContentReferenceType::BareNaddr->value => '/(?<![a-z0-9])naddr1(?:(?!nostr:)[a-z0-9])+(?=nostr:|[^a-z0-9]|$)/i',
            ContentReferenceType::LegacyRef->value => '/#\[(\d+)\]/',
        ];

        foreach ($patterns as $typeValue => $pattern) {
            if (preg_match_all($pattern, $contentString, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $position = $match[1];
                    $length = strlen($match[0]);

                    $overlaps = false;
                    foreach ($usedPositions as $usedRange) {
                        if ($position < $usedRange['end'] && $position + $length > $usedRange['start']) {
                            $overlaps = true;
                            break;
                        }
                    }

                    if (!$overlaps) {
                        $cleanRef = str_replace('nostr:', '', $match[0]);
                        $decoded = $this->decodeBech32Reference($cleanRef);

                        $pubkeyHex = $decoded['pubkey'] ?? $decoded['author'] ?? null;

                        $references[] = new ContentReference(
                            ContentReferenceType::from($typeValue),
                            $match[0],
                            $cleanRef,
                            $match[1],
                            $decoded['type'] ?? 'unknown',
                            isset($decoded['event_id']) ? EventId::fromHex($decoded['event_id']) : null,
                            null !== $pubkeyHex ? PublicKey::fromHex($pubkeyHex) : null,
                            $this->parseRelayUrls($decoded['relays'] ?? []),
                            $decoded['error'] ?? null,
                            $decoded['identifier'] ?? null,
                            $decoded['kind'] ?? null
                        );

                        $usedPositions[] = ['start' => $position, 'end' => $position + $length];
                    }
                }
            }
        }

        usort($references, static fn ($a, $b) => $a->getPosition() <=> $b->getPosition());

        return $references;
    }

    private function decodeBech32Reference(string $bech32): array
    {
        try {
            return $this->bech32Encoder->decodeComplexEntity($bech32);
        } catch (Exception $e) {
            return [
                'decoded_type' => 'invalid',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function parseRelayUrls(array $relayStrings): array
    {
        return array_values(array_filter(
            array_map(static fn (string $url) => RelayUrl::fromString($url), $relayStrings)
        ));
    }
}
