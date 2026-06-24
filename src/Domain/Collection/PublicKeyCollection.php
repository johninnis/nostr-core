<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

/**
 * @extends TypedCollection<PublicKey>
 */
final class PublicKeyCollection extends TypedCollection
{
    // Deliberate: lazily memoised membership index, permitted because the collection is not readonly — see ADR-0024
    /** @var array<string, true>|null */
    private ?array $hexIndex = null;

    #[Override]
    protected function elementType(): string
    {
        return PublicKey::class;
    }

    public static function fromHexValues(mixed $values): self
    {
        return new self(self::parseStrings($values, PublicKey::fromHex(...)));
    }

    public function unique(): self
    {
        return new self($this->deduplicate(static fn (PublicKey $publicKey): string => $publicKey->toHex()));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return array_map(static fn (PublicKey $publicKey): string => $publicKey->toHex(), $this->items);
    }

    public function contains(PublicKey $publicKey): bool
    {
        $this->hexIndex ??= array_fill_keys($this->toHexes(), true);

        return isset($this->hexIndex[$publicKey->toHex()]);
    }

    public function intersect(self $other): self
    {
        return new self($this->retainByKey($other, static fn (PublicKey $publicKey): string => $publicKey->toHex(), true));
    }

    public function diff(self $other): self
    {
        return new self($this->retainByKey($other, static fn (PublicKey $publicKey): string => $publicKey->toHex(), false));
    }
}
