<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class Rumour
{
    public function __construct(
        private PublicKey $pubkey,
        private Timestamp $createdAt,
        private EventKind $kind,
        private TagCollection $tags,
        private EventContent $content,
    ) {
    }

    public function sign(KeyPair $keyPair, SignatureServiceInterface $signatureService): Event
    {
        if (!$keyPair->getPublicKey()->equals($this->pubkey)) {
            throw new InvalidArgumentException('Key pair does not match rumour public key');
        }

        $id = $this->getId();
        $signature = $signatureService->sign($keyPair->getPrivateKey(), $id->toBytes());

        return new Event($this, $id, $signature);
    }

    public function getId(): EventId
    {
        $serialised = JsonWireFormat::encode([
            0,
            $this->pubkey->toHex(),
            $this->createdAt->toInt(),
            $this->kind->toInt(),
            $this->tags->toJsonArray(),
            (string) $this->content,
        ], JsonWireFormat::EVENT);

        return EventId::tryFromBytes(hash('sha256', $serialised, true))
            ?? throw new InvalidEventException('Hashed event ID was not a valid 32-byte value');
    }

    public function getPubkey(): PublicKey
    {
        return $this->pubkey;
    }

    public function getCreatedAt(): Timestamp
    {
        return $this->createdAt;
    }

    public function getKind(): EventKind
    {
        return $this->kind;
    }

    public function getTags(): TagCollection
    {
        return $this->tags;
    }

    public function withTags(TagCollection $tags): self
    {
        return new self($this->pubkey, $this->createdAt, $this->kind, $tags, $this->content);
    }

    public function getContent(): EventContent
    {
        return $this->content;
    }

    public function isReply(): bool
    {
        if ($this->kind->is(EventKind::REPOST) || $this->kind->is(EventKind::GENERIC_REPOST)) {
            return false;
        }

        if ($this->kind->is(EventKind::COMMENT)) {
            return true;
        }

        $eTags = $this->tags->findByType(TagType::event());

        return array_any($eTags, static fn (Tag $tag): bool => in_array($tag->getValue(2), [Nip10Marker::Root->value, Nip10Marker::Reply->value, null, ''], true));
    }

    public function isRepost(): bool
    {
        return $this->kind->is(EventKind::REPOST) || $this->kind->is(EventKind::GENERIC_REPOST);
    }

    public function isDeletion(): bool
    {
        return $this->kind->is(EventKind::EVENT_DELETION);
    }

    public function isExpired(): bool
    {
        $value = $this->tags->getFirstValueByType(TagType::expiration());
        if (null === $value) {
            return false;
        }

        $seconds = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $seconds) {
            return false;
        }

        $expiry = Timestamp::tryFromInt($seconds);

        return null !== $expiry && $expiry->hasPassed();
    }

    public function isProtected(): bool
    {
        return $this->tags->hasType(TagType::protected());
    }

    public function getPublishedAt(): ?Timestamp
    {
        $value = $this->tags->getFirstValueByType(TagType::fromString(TagType::PUBLISHED_AT));
        if (null === $value) {
            return null;
        }

        $seconds = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $seconds) {
            return null;
        }

        return Timestamp::tryFromInt($seconds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId()->toHex(),
            'pubkey' => $this->pubkey->toHex(),
            'created_at' => $this->createdAt->toInt(),
            'kind' => $this->kind->toInt(),
            'tags' => $this->tags->toJsonArray(),
            'content' => (string) $this->content,
        ];
    }

    public function toJson(): string
    {
        return JsonWireFormat::encode($this->toArray(), JsonWireFormat::EVENT);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function tryFromArray(array $data): ?self
    {
        foreach (['pubkey', 'created_at', 'kind', 'tags', 'content'] as $field) {
            if (!array_key_exists($field, $data)) {
                return null;
            }
        }

        if (!is_string($data['pubkey']) || !is_int($data['created_at']) || !is_int($data['kind'])) {
            return null;
        }

        $pubkey = PublicKey::tryFromHex($data['pubkey']);
        if (null === $pubkey) {
            return null;
        }

        $createdAt = Timestamp::tryFromInt($data['created_at']);
        if (null === $createdAt) {
            return null;
        }

        $kind = EventKind::tryFromInt($data['kind']);
        if (null === $kind) {
            return null;
        }

        $tags = TagCollection::tryFromArray($data['tags']);
        if (null === $tags) {
            return null;
        }

        $content = $data['content'];
        if (!is_string($content)) {
            // Deliberate: coerces non-string content to the canonical event JSON string rather than rejecting it — see ADR-0022
            $content = json_encode($content, JsonWireFormat::EVENT);
            if (false === $content) {
                return null;
            }
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            return null;
        }

        return new self($pubkey, $createdAt, $kind, $tags, EventContent::fromString($content));
    }
}
