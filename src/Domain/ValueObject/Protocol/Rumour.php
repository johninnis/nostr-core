<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Collection\PublicKeyCollection;
use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\EventKindCategory;
use Innis\Nostr\Core\Domain\Exception\InvalidEventException;
use Innis\Nostr\Core\Domain\Failure\RumourParseFailure;
use Innis\Nostr\Core\Domain\Service\ExpirationDerivation;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\ReplyChainAnalyser;
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
    private function __construct(
        private PublicKey $pubkey,
        private Timestamp $createdAt,
        private EventKind $kind,
        private TagCollection $tags,
        private EventContent $content,
    ) {
    }

    // Deliberate: the one way to build an unsigned event; created_at is read directly, not clock-injected — see ADR-0081
    public static function draft(
        PublicKey $pubkey,
        EventKind $kind,
        ?EventContent $content = null,
        ?TagCollection $tags = null,
        ?Timestamp $createdAt = null,
    ): self {
        return new self(
            $pubkey,
            $createdAt ?? Timestamp::now(),
            $kind,
            self::withAddressIdentifier($kind, $tags ?? new TagCollection()),
            $content ?? EventContent::empty(),
        );
    }

    // Deliberate: an addressable draft with no d tag gains ["d", ""], so nothing built here relies on readers treating a missing d as the empty identifier — see nostr-adrs ADR-0007
    private static function withAddressIdentifier(EventKind $kind, TagCollection $tags): TagCollection
    {
        return EventKindCategory::Addressable === $kind->category() && !$tags->hasType(TagType::identifier())
            ? $tags->add(Tag::identifier(''))
            : $tags;
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
        $serialised = JsonWireFormat::encode([0, ...array_values($this->toArrayWithoutId())], JsonWireFormat::EVENT);

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
        return new self($this->pubkey, $this->createdAt, $this->kind, self::withAddressIdentifier($this->kind, $tags), $this->content);
    }

    public function withCreatedAt(Timestamp $createdAt): self
    {
        return new self($this->pubkey, $createdAt, $this->kind, $this->tags, $this->content);
    }

    /**
     * This rumour with one NIP-40 `expiration` tag naming $expiresAt, replacing any it carries — a reader tolerates
     * many (shared ADR-0011), but a writer emits exactly one.
     */
    public function withExpiration(Timestamp $expiresAt): self
    {
        $tags = [];
        foreach ($this->tags as $tag) {
            if (!$tag->getType()->equals(TagType::expiration())) {
                $tags[] = $tag;
            }
        }
        $tags[] = Tag::fromArray([TagType::EXPIRATION, (string) $expiresAt->toInt()]);

        return $this->withTags(new TagCollection($tags));
    }

    public function getContent(): EventContent
    {
        return $this->content;
    }

    // Deliberate: delegates so the package has one answer to this question — a mention-marked e tag is not a reply, and neither is an unparseable one — see ADR-0085
    public function isReply(): bool
    {
        return ReplyChainAnalyser::analyse($this->tags, $this->kind)->isReply();
    }

    public function isRepost(): bool
    {
        return $this->kind->is(EventKind::REPOST) || $this->kind->is(EventKind::GENERIC_REPOST);
    }

    public function isDeletion(): bool
    {
        return $this->kind->is(EventKind::EVENT_DELETION);
    }

    // Deliberate: the derivation is public so a store can answer from the expiration values it indexed, without decoding the event — see ADR-0071
    public function expiresAt(): ?Timestamp
    {
        return ExpirationDerivation::earliestStated($this->tags->getValuesByType(TagType::expiration()));
    }

    public function isExpiredAt(Timestamp $reference): bool
    {
        return $this->expiresAt()?->hasPassedAt($reference) ?? false;
    }

    public function isProtected(): bool
    {
        return array_any(
            $this->tags->findByType(TagType::protected()),
            static fn (Tag $tag): bool => [] === $tag->getValues(),
        );
    }

    public function getPublishedAt(): ?Timestamp
    {
        return $this->tags->getPublishedAt();
    }

    public function getChatRoom(): PublicKeyCollection
    {
        $members = new PublicKeyCollection([$this->pubkey, ...$this->tags->getPubkeys()])->unique()->toArray();
        usort($members, static fn (PublicKey $one, PublicKey $other): int => strcmp($one->toHex(), $other->toHex()));

        return new PublicKeyCollection($members);
    }

    /**
     * @return array{id: string, pubkey: string, created_at: int, kind: int, tags: list<list<string>>, content: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->getId()->toHex(), ...$this->toArrayWithoutId()];
    }

    /**
     * @return array{pubkey: string, created_at: int, kind: int, tags: list<list<string>>, content: string}
     */
    public function toArrayWithoutId(): array
    {
        return [
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

    public static function tryFromArray(mixed $data): self|RumourParseFailure
    {
        if (!is_array($data)) {
            return RumourParseFailure::Malformed;
        }

        $rumour = self::tryFromFields($data);

        if (null === $rumour || !array_key_exists('id', $data)) {
            return RumourParseFailure::Malformed;
        }

        return $data['id'] === $rumour->getId()->toHex() ? $rumour : RumourParseFailure::IdMismatch;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function tryFromFields(array $data): ?self
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

        $content = is_string($data['content']) ? EventContent::tryFromString($data['content']) : null;
        if (null === $content) {
            return null;
        }

        return new self($pubkey, $createdAt, $kind, $tags, $content);
    }
}
