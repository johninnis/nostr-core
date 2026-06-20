<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Entity;

use Innis\Nostr\Core\Domain\Enum\Nip10Marker;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Identity\Signature;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use InvalidArgumentException;

final readonly class Event
{
    // JSON_UNESCAPED_LINE_TERMINATORS: NIP-01 ids require U+2028/U+2029 emitted verbatim, which PHP
    // escapes even under JSON_UNESCAPED_UNICODE — without it ids are irreproducible for such content.
    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    public function __construct(
        private PublicKey $pubkey,
        private Timestamp $createdAt,
        private EventKind $kind,
        private TagCollection $tags,
        private EventContent $content,
        private ?EventId $id = null,
        private ?Signature $signature = null,
        private ?string $rawJson = null,
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
        $serialised = json_encode([
            0,
            $this->pubkey->toHex(),
            $this->createdAt->toInt(),
            $this->kind->toInt(),
            $this->tags->toArray(),
            (string) $this->content,
        ], self::JSON_FLAGS);

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

        return new self(
            $this->pubkey,
            $this->createdAt,
            $this->kind,
            $this->tags,
            $this->content,
            $this->id,
            $this->signature,
            $this->encodeJson(),
        );
    }

    private function encodeJson(): string
    {
        $json = json_encode($this->toArray(), self::JSON_FLAGS);

        if (false === $json) {
            throw new InvalidEventException('Failed to serialise event as JSON');
        }

        return $json;
    }

    public function isSigned(): bool
    {
        return null !== $this->signature;
    }

    public function isReply(): bool
    {
        if ($this->kind->equals(EventKind::repost()) || $this->kind->equals(EventKind::genericRepost())) {
            return false;
        }

        if ($this->kind->equals(EventKind::comment())) {
            return true;
        }

        $eTags = $this->tags->findByType(TagType::event());

        return array_any($eTags, static fn (Tag $tag): bool => in_array($tag->getValue(2), [Nip10Marker::Root->value, Nip10Marker::Reply->value, null, ''], true));
    }

    public function isRepost(): bool
    {
        return $this->kind->equals(EventKind::repost()) || $this->kind->equals(EventKind::genericRepost());
    }

    public function isDeletion(): bool
    {
        return $this->kind->equals(EventKind::eventDeletion());
    }

    public function isExpired(): bool
    {
        $values = $this->tags->getValuesByType(TagType::expiration());

        if (empty($values)) {
            return false;
        }

        return Timestamp::fromInt((int) reset($values))->hasPassed();
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
        return self::build($data, null);
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new InvalidEventException('Event JSON must decode to an object');
        }

        return self::build($data, $json);
    }

    private static function build(array $data, ?string $rawJson): self
    {
        $requiredFields = ['pubkey', 'created_at', 'kind', 'tags', 'content'];
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidEventException("Missing required field: {$field}");
            }
        }

        $content = $data['content'];
        if (!is_string($content)) {
            $encoded = json_encode($content, JSON_UNESCAPED_SLASHES);
            if (false === $encoded) {
                throw new InvalidEventException('Failed to encode content as JSON');
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
            $signature,
            $rawJson,
        );
    }

    public function __toString(): string
    {
        return null !== $this->id ? (string) $this->id : '';
    }
}
