<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\IdentityKeyedInterface;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;
use Stringable;

final readonly class Event implements Stringable, IdentityKeyedInterface
{
    public function __construct(
        private Rumour $rumour,
        private EventId $id,
        private Signature $signature,
    ) {
    }

    public function verify(SignatureServiceInterface $signatureService): bool
    {
        if (!$this->id->equals($this->rumour->getId())) {
            return false;
        }

        return $signatureService->verify($this->rumour->getPubkey(), $this->id->toBytes(), $this->signature);
    }

    public function getRumour(): Rumour
    {
        return $this->rumour;
    }

    // Deliberate: returns the stored id it was signed with and never rehashes; the per-read hash lives on Rumour — see ADR-0046
    public function getId(): EventId
    {
        return $this->id;
    }

    #[Override]
    public function identityKey(): string
    {
        return $this->id->identityKey();
    }

    public function getPubkey(): PublicKey
    {
        return $this->rumour->getPubkey();
    }

    public function getCreatedAt(): Timestamp
    {
        return $this->rumour->getCreatedAt();
    }

    public function getKind(): EventKind
    {
        return $this->rumour->getKind();
    }

    public function getTags(): TagCollection
    {
        return $this->rumour->getTags();
    }

    public function getContent(): EventContent
    {
        return $this->rumour->getContent();
    }

    public function getSignature(): Signature
    {
        return $this->signature;
    }

    // Deliberate: always encodes the fields this event holds, never the bytes it was parsed from, so the type vouches only for what it verified — see ADR-0076
    public function toJson(): string
    {
        return JsonWireFormat::encode($this->toArray(), JsonWireFormat::EVENT);
    }

    public function isReply(): bool
    {
        return $this->rumour->isReply();
    }

    public function isRepost(): bool
    {
        return $this->rumour->isRepost();
    }

    public function isDeletion(): bool
    {
        return $this->rumour->isDeletion();
    }

    public function expiresAt(): ?Timestamp
    {
        return $this->rumour->expiresAt();
    }

    public function isExpiredAt(Timestamp $reference): bool
    {
        return $this->rumour->isExpiredAt($reference);
    }

    public function isProtected(): bool
    {
        return $this->rumour->isProtected();
    }

    public function getPublishedAt(): ?Timestamp
    {
        return $this->rumour->getPublishedAt();
    }

    /**
     * @return array{id: string, pubkey: string, created_at: int, kind: int, tags: list<list<string>>, content: string, sig: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toHex(),
            ...$this->rumour->toArrayWithoutId(),
            'sig' => $this->signature->toHex(),
        ];
    }

    public static function tryFromJson(string $json): ?self
    {
        return self::tryFromArray(JsonWireFormat::decode($json));
    }

    public static function tryFromArray(mixed $value): ?self
    {
        if (!is_array($value)) {
            return null;
        }

        $rumour = Rumour::tryFromFields($value);
        $id = is_string($value['id'] ?? null) ? EventId::tryFromHex($value['id']) : null;
        $signature = is_string($value['sig'] ?? null) ? Signature::tryFromHex($value['sig']) : null;

        return null === $rumour || null === $id || null === $signature ? null : new self($rumour, $id, $signature);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id->toHex();
    }
}
