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
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Override;
use Stringable;

final readonly class Event implements Stringable
{
    public function __construct(
        private Rumour $rumour,
        private EventId $id,
        private Signature $signature,
        private ?string $rawJson = null,
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

    public function getRawJson(): ?string
    {
        return $this->rawJson;
    }

    public function toJson(): string
    {
        return $this->rawJson ?? $this->encodeJson();
    }

    public function withRawJson(): self
    {
        if (null !== $this->rawJson) {
            return $this;
        }

        return new self($this->rumour, $this->id, $this->signature, $this->encodeJson());
    }

    private function encodeJson(): string
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

    public function isExpired(): bool
    {
        return $this->rumour->isExpired();
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toHex(),
            'pubkey' => $this->rumour->getPubkey()->toHex(),
            'created_at' => $this->rumour->getCreatedAt()->toInt(),
            'kind' => $this->rumour->getKind()->toInt(),
            'tags' => $this->rumour->getTags()->toJsonArray(),
            'content' => (string) $this->rumour->getContent(),
            'sig' => $this->signature->toHex(),
        ];
    }

    public static function tryFromArray(mixed $value): ?self
    {
        return is_array($value) ? self::build($value, null) : null;
    }

    public static function tryFromJson(string $json): ?self
    {
        $data = JsonWireFormat::decodeArray($json);

        if (null === $data) {
            return null;
        }

        return self::build($data, $json);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function build(array $data, ?string $rawJson): ?self
    {
        $rumour = Rumour::tryFromArray($data);
        if (null === $rumour) {
            return null;
        }

        if (!isset($data['id']) || !is_string($data['id'])) {
            return null;
        }

        $id = EventId::tryFromHex($data['id']);
        if (null === $id) {
            return null;
        }

        if (!isset($data['sig']) || !is_string($data['sig']) || '' === $data['sig']) {
            return null;
        }

        $signature = Signature::tryFromHex($data['sig']);
        if (null === $signature) {
            return null;
        }

        return new self($rumour, $id, $signature, $rawJson);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id->toHex();
    }
}
