<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\RelayUrlCollection;
use Innis\Nostr\Core\Domain\Collection\WebUrlCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Service\P2pkSecretParser;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;

final readonly class NutzapInfo
{
    private function __construct(
        private PublicKey $pubkey,
        private PublicKey $p2pkPubkey,
        private WebUrlCollection $mints,
        private RelayUrlCollection $relays,
    ) {
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getP2pkPubkey(): PublicKey
    {
        return $this->p2pkPubkey;
    }

    public function getMints(): WebUrlCollection
    {
        return $this->mints;
    }

    public function getRelays(): RelayUrlCollection
    {
        return $this->relays;
    }

    // Deliberate: no one P2PK key, or the author's own, is no nutzap info, never a fall-back to the author's key — see nostr-adrs ADR-0121
    public static function tryFromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::NUTZAP_MINT_RECOMMENDATION)) {
            return null;
        }

        $tags = $event->getTags();
        $p2pkHex = $tags->getSoleValueByType(TagType::fromString(TagType::P2PK_PUBKEY), self::xCoordinateOf(...))->getValue();
        $p2pkPubkey = null === $p2pkHex ? null : PublicKey::tryFromHex($p2pkHex);

        if (null === $p2pkPubkey || $p2pkPubkey->equals($event->getPubkey())) {
            return null;
        }

        return new self(
            $event->getPubkey(),
            $p2pkPubkey,
            WebUrlCollection::fromStrings($tags->getValuesByType(TagType::fromString(TagType::ACCEPTED_MINT))),
            RelayUrlCollection::fromStrings($tags->getValuesByType(TagType::fromString(TagType::RELAY))),
        );
    }

    private static function xCoordinateOf(string $key): ?string
    {
        return (P2pkSecretParser::tryParseCompressedKey($key) ?? PublicKey::tryFromHex(strtolower($key)))?->toHex();
    }
}
