<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class Event
{
    public function __construct(
        private PublicKey $pubkey,
        private Timestamp $createdAt,
        private EventKind $kind,
        private TagCollection $tags,
        private EventContent $content,
        private ?EventId $id = null,
        private ?Signature $signature = null,
    ) {
    }

    public function sign(KeyPair $keyPair, SignatureServiceInterface $signatureService): self
    {
        if (!$keyPair->getPublicKey()->equals($this->pubkey)) {
            throw new InvalidArgumentException('Key pair does not match event public key');
        }

        $id = $this->calculateId();
        $signature = $signatureService->sign($keyPair->getPrivateKey(), $id->toBytes());

        return new self($this->pubkey, $this->createdAt, $this->kind, $this->tags, $this->content, $id, $signature);
    }

    public function verify(SignatureServiceInterface $signatureService): bool
    {
        if (null === $this->signature || null === $this->id) {
            return false;
        }

        if (!$this->id->equals($this->calculateId())) {
            return false;
        }

        return $signatureService->verify($this->pubkey, $this->id->toBytes(), $this->signature);
    }

    public function calculateId(): EventId
    {
        // JSON_UNESCAPED_LINE_TERMINATORS is required for NIP-01 compliance:
        // U+2028 and U+2029 must be emitted verbatim, but PHP escapes them as
        // \u2028 / \u2029 by default even with JSON_UNESCAPED_UNICODE. Without
        // this flag, ids cannot be reproduced for any event whose content
        // contains a line or paragraph separator.
        $serialised = json_encode([
            0,
            $this->pubkey->toHex(),
            $this->createdAt->toInt(),
            $this->kind->toInt(),
            $this->tags->toArray(),
            (string) $this->content,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);

        if (false === $serialised) {
            throw new InvalidEventException('Failed to serialise event for ID calculation');
        }

        $id = EventId::fromBytes(hash('sha256', $serialised, true));
        assert(null !== $id);

        return $id;
    }

    public function getId(): EventId
    {
        return $this->id ?? $this->calculateId();
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

    public function getSignature(): ?Signature
    {
        return $this->signature;
    }

    public function isSigned(): bool
    {
        return null !== $this->signature;
    }

    public function isReply(): bool
    {
        $kindInt = $this->kind->toInt();

        if (EventKind::REPOST === $kindInt || EventKind::GENERIC_REPOST === $kindInt) {
            return false;
        }

        if (EventKind::COMMENT === $kindInt) {
            return true;
        }

        $eTags = $this->tags->findByType(TagType::event());
        if (empty($eTags)) {
            return false;
        }

        // Per NIP-10: check markers on e tags
        // - "root" or "reply" marker = IS a reply
        // - "mention" marker = NOT a reply (just an inline reference)
        // - No marker (deprecated positional scheme) = IS a reply
        foreach ($eTags as $tag) {
            $marker = $tag->getValue(2);

            if ('root' === $marker || 'reply' === $marker) {
                return true;
            }

            if (null === $marker || '' === $marker) {
                return true;
            }
        }

        return false;
    }

    public function isRepost(): bool
    {
        $kindInt = $this->kind->toInt();

        return EventKind::REPOST === $kindInt || EventKind::GENERIC_REPOST === $kindInt;
    }

    public function isDeletion(): bool
    {
        return EventKind::EVENT_DELETION === $this->kind->toInt();
    }

    public function isExpired(): bool
    {
        $values = $this->tags->getValuesByType(TagType::expiration());

        if (empty($values)) {
            return false;
        }

        $expiration = (int) reset($values);

        return time() >= $expiration;
    }

    public function isProtected(): bool
    {
        return $this->tags->hasType(TagType::protected());
    }

    public function getPublishedAt(): ?Timestamp
    {
        $values = $this->tags->getValuesByType(TagType::fromString('published_at'));

        if (empty($values)) {
            return null;
        }

        return Timestamp::fromInt((int) reset($values));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId()->toHex(),
            'pubkey' => $this->pubkey->toHex(),
            'created_at' => $this->createdAt->toInt(),
            'kind' => $this->kind->toInt(),
            'tags' => $this->tags->toArray(),
            'content' => (string) $this->content,
            'sig' => $this->signature?->toHex() ?? '',
        ];
    }

    public static function fromArray(array $data): self
    {
        $requiredFields = ['pubkey', 'created_at', 'kind', 'tags', 'content'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $content = $data['content'];
        if (!is_string($content)) {
            $encoded = json_encode($content, JSON_UNESCAPED_SLASHES);
            if (false === $encoded) {
                throw new InvalidArgumentException('Failed to encode content as JSON');
            }
            $content = $encoded;
        }

        $id = isset($data['id']) ? EventId::fromHex($data['id']) : null;

        $signature = null;
        if (isset($data['sig']) && '' !== $data['sig']) {
            $signature = Signature::fromHex($data['sig']);
        }

        return new self(
            PublicKey::fromHex($data['pubkey']) ?? throw new InvalidEventException('Invalid pubkey in event data'),
            Timestamp::fromInt($data['created_at']),
            EventKind::fromInt($data['kind']),
            TagCollection::fromArray($data['tags']),
            EventContent::fromString($content),
            $id,
            $signature
        );
    }

    public function __toString(): string
    {
        return null !== $this->id ? (string) $this->id : '';
    }
}
