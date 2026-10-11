<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Collection;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Payment\MintKeyset;
use Override;

/**
 * @extends TypedCollection<MintKeyset>
 */
final class MintKeysetCollection extends TypedCollection
{
    #[Override]
    protected function elementType(): string
    {
        return MintKeyset::class;
    }

    public static function fromKeysResponse(mixed $response): self
    {
        $keysets = JsonWireFormat::objectFields($response)['keysets'] ?? null;

        return self::fromEach(is_array($keysets) && array_is_list($keysets) ? $keysets : [], MintKeyset::tryFromArray(...));
    }

    public function findById(string $id): ?MintKeyset
    {
        return array_find($this->items, static fn (MintKeyset $keyset): bool => $keyset->getId() === $id);
    }
}
