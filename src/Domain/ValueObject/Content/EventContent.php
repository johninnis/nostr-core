<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

final readonly class EventContent
{
    public function __construct(private string $content)
    {
    }

    public function isEmpty(): bool
    {
        return $this->content === '';
    }

    public function getLength(): int
    {
        return mb_strlen($this->content, 'UTF-8');
    }

    public function equals(EventContent $other): bool
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

    public function extractHashtags(): array
    {
        preg_match_all('/(?<![&\w])#([a-zA-Z0-9_]+)/u', $this->content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $hashtags = array_map('strtolower', $matches[1]);

        return array_values(array_unique($hashtags));
    }

    public function __toString(): string
    {
        return $this->content;
    }
}
