<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\Service\P2pkSecretParser;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

final readonly class NutzapProof
{
    private function __construct(
        private ?string $keysetId,
        private int $amount,
        private ?string $secret,
        private ?string $unblindedSignature,
        private ?NutzapDleq $dleq,
        private ?PublicKey $lockKey,
    ) {
    }

    public function getKeysetId(): ?string
    {
        return $this->keysetId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function getUnblindedSignature(): ?string
    {
        return $this->unblindedSignature;
    }

    public function getDleq(): ?NutzapDleq
    {
        return $this->dleq;
    }

    public function getLockKey(): ?PublicKey
    {
        return $this->lockKey;
    }

    public function isLockedTo(PublicKey $key): bool
    {
        return $this->lockKey?->equals($key) ?? false;
    }

    // Deliberate: the proof is carried whole as written, a field absent when missing or mistyped, for the DLEQ check against its mint's keyset — see nostr-adrs ADR-0121
    public static function tryFromJson(string $json): ?self
    {
        $proof = JsonWireFormat::decodeObject($json);
        $amount = null === $proof ? null : JsonWireFormat::intField($proof, 'amount');

        if (null === $proof || null === $amount) {
            return null;
        }

        $secret = JsonWireFormat::stringField($proof, 'secret');

        return new self(
            JsonWireFormat::stringField($proof, 'id'),
            $amount,
            $secret,
            JsonWireFormat::stringField($proof, 'C'),
            NutzapDleq::tryFromArray($proof['dleq'] ?? null),
            null === $secret ? null : P2pkSecretParser::tryParseLockKey($secret),
        );
    }
}
