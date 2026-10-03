<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Sha256Hash;
use InvalidArgumentException;

final readonly class FileEventMetadata
{
    private function __construct(private FileMetadata $metadata)
    {
    }

    // Deliberate: a second type beside FileMetadata, because a kind 1063 event must state what a BUD-08 descriptor or an imeta tag may leave out — see ADR-0095
    public static function tryFrom(FileMetadata $metadata): ?self
    {
        $complete = null !== $metadata->getMimeType()
            && self::isSha256Hex($metadata->getHash())
            && self::isSha256Hex($metadata->getOriginalHash());

        return $complete ? new self($metadata) : null;
    }

    public static function from(FileMetadata $metadata): self
    {
        return self::tryFrom($metadata)
            ?? throw new InvalidArgumentException('A kind 1063 file metadata event states a MIME type and the SHA-256 hex of the file and of the original file');
    }

    public static function tryFromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::FILE_METADATA)) {
            return null;
        }

        $metadata = FileMetadata::tryFromTagCollection($event->getTags());

        return null === $metadata ? null : self::tryFrom($metadata);
    }

    public function getMetadata(): FileMetadata
    {
        return $this->metadata;
    }

    public function toTags(): TagCollection
    {
        return $this->metadata->toTags();
    }

    private static function isSha256Hex(?string $hash): bool
    {
        return null !== $hash && null !== Sha256Hash::tryFromHex($hash);
    }
}
