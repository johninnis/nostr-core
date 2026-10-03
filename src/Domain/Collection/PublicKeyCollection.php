<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Override;

/**
 * @extends KeyedCollection<PublicKey>
 */
final class PublicKeyCollection extends KeyedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return PublicKey::class;
    }

    private static function tryParse(mixed $value): ?PublicKey
    {
        return is_string($value) ? PublicKey::tryFromHex($value) : null;
    }

    public static function fromHexValues(mixed $values): self
    {
        return self::fromEach($values, self::tryParse(...));
    }

    public static function tryFromArray(mixed $values): ?self
    {
        return self::tryFromEach($values, self::tryParse(...));
    }

    /**
     * @return list<string>
     */
    public function toHexes(): array
    {
        return $this->mapItems(static fn (PublicKey $publicKey): string => $publicKey->toHex());
    }
}
