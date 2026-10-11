<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Service\DecimalIntegerParser;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

final readonly class MintKeyset
{
    /**
     * @param array<non-negative-int, string> $keysByAmount
     */
    private function __construct(
        private string $id,
        private string $unit,
        private array $keysByAmount,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function getKeyFor(int $amount): ?string
    {
        return $this->keysByAmount[$amount] ?? null;
    }

    // Deliberate: the id, the unit and each key are kept as written, and whether a key is a curve point is left to the DLEQ check — see nostr-adrs ADR-0121
    public static function tryFromArray(mixed $data): ?self
    {
        $fields = JsonWireFormat::objectFields($data);
        $id = null === $fields ? null : JsonWireFormat::stringField($fields, 'id');
        $unit = null === $fields ? null : JsonWireFormat::stringField($fields, 'unit');
        $keys = null === $fields ? null : JsonWireFormat::objectField($fields, 'keys');

        return null === $id || null === $unit || null === $keys ? null : new self($id, $unit, self::keysByAmount($keys));
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return array<non-negative-int, string>
     */
    private static function keysByAmount(array $keys): array
    {
        $keysByAmount = [];

        foreach ($keys as $amount => $key) {
            $parsedAmount = DecimalIntegerParser::tryParse((string) $amount);

            if (null !== $parsedAmount && is_string($key)) {
                $keysByAmount[$parsedAmount] = $key;
            }
        }

        return $keysByAmount;
    }
}
