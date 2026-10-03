<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use Innis\Nostr\Core\Domain\ValueObject\Protocol\HttpUrl;
use Override;
use Stringable;

final readonly class ExternalContentId implements Stringable
{
    private function __construct(
        private string $value,
        private string $kind,
        private ?HttpUrl $hint,
    ) {
    }

    // Deliberate: the identifier is paired with its NIP-73 type, read from the K or k tag beside it, so neither may be empty — see ADR-0085
    public static function tryFromString(string $value, string $kind, ?string $hint = null): ?self
    {
        $isText = '' !== $value && '' !== $kind && mb_check_encoding($value, 'UTF-8') && mb_check_encoding($kind, 'UTF-8');

        return $isText ? new self($value, $kind, HttpUrl::tryFromString($hint)) : null;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getHint(): ?HttpUrl
    {
        return $this->hint;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $data = ['value' => $this->value, 'kind' => $this->kind];

        if (null !== $this->hint) {
            $data['hint'] = (string) $this->hint;
        }

        return $data;
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $value = $data['value'] ?? null;
        $kind = $data['kind'] ?? null;
        $hint = $data['hint'] ?? null;

        if (!is_string($value) || !is_string($kind) || (null !== $hint && !is_string($hint))) {
            return null;
        }

        return self::tryFromString($value, $kind, $hint);
    }
}
