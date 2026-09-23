<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Collection\HashtagCollection;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Hashtag;
use Override;
use Stringable;

final readonly class EventContent implements Stringable
{
    private function __construct(private string $content)
    {
    }

    public function isEmpty(): bool
    {
        return '' === $this->content;
    }

    public function getLength(): int
    {
        return mb_strlen($this->content, 'UTF-8');
    }

    public function equals(self $other): bool
    {
        return $this->content === $other->content;
    }

    public static function fromString(string $content): self
    {
        return new self($content);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function extractHashtags(): HashtagCollection
    {
        preg_match_all('/(?<![&\w])#([a-zA-Z0-9_]+)/u', $this->content, $matches);

        return new HashtagCollection(array_map(Hashtag::fromString(...), $matches[1]))->unique();
    }

    #[Override]
    public function __toString(): string
    {
        return $this->content;
    }
}
