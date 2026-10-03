<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\HexCodec;
use InvalidArgumentException;
use Override;
use Stringable;

final readonly class Sha256Hash implements Stringable
{
    private const int BYTE_LENGTH = 32;

    /**
     * @param non-empty-string $hex
     */
    private function __construct(private string $hex)
    {
    }

    public static function tryFromHex(string $hex): ?self
    {
        $canonical = HexCodec::tryCanonical($hex, self::BYTE_LENGTH);

        return null === $canonical ? null : new self($canonical);
    }

    public static function fromHex(string $hex): self
    {
        return self::tryFromHex($hex) ?? throw new InvalidArgumentException('A SHA-256 hash is 64 lowercase hexadecimal characters');
    }

    public static function ofContent(string $content): self
    {
        return self::fromHex(hash('sha256', $content));
    }

    public function isOfEmptyContent(): bool
    {
        return $this->equals(self::ofContent(''));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hex, $other->hex);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->hex;
    }
}
