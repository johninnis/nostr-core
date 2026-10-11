<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

final readonly class NutzapDleq
{
    private function __construct(
        private ?string $e,
        private ?string $s,
        private ?string $r,
    ) {
    }

    public function getE(): ?string
    {
        return $this->e;
    }

    public function getS(): ?string
    {
        return $this->s;
    }

    public function getR(): ?string
    {
        return $this->r;
    }

    public static function tryFromArray(mixed $data): ?self
    {
        $fields = JsonWireFormat::objectFields($data);

        return null === $fields ? null : new self(
            JsonWireFormat::stringField($fields, 'e'),
            JsonWireFormat::stringField($fields, 's'),
            JsonWireFormat::stringField($fields, 'r'),
        );
    }
}
