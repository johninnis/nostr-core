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
    #[Override]
    protected function elementType(): string
    {
        return PublicKey::class;
    }

    private static function keyOf(PublicKey $publicKey): string
    {
        return $publicKey->toHex();
    }

    private static function tryParse(mixed $value): ?PublicKey
    {
        return is_string($value) ? PublicKey::fromHex($value) : null;
    }

    public static function fromHexValues(mixed $values): self
    {
        return new self(self::parseEach($values, self::tryParse(...)));
    }

    public static function fromWire(mixed $values): ?self
    {
        $publicKeys = self::parseEachStrict($values, self::tryParse(...));

        return null === $publicKeys ? null : new self($publicKeys);
    }

    public function unique(): self
    {
        return new self($this->deduplicate(self::keyOf(...)));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(self::keyOf(...));
    }

    public function contains(PublicKey $publicKey): bool
    {
        return $this->containsByKey(self::keyOf($publicKey), self::keyOf(...));
    }

    public function intersect(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), true));
    }

    public function diff(self $other): self
    {
        return new self($this->retainByKey($other, self::keyOf(...), false));
    }
}
