<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;

final readonly class GiftWrapEnvelope
{
    public function __construct(
        private KeyPair $ephemeralKeyPair,
        private Timestamp $sealTimestamp,
        private Timestamp $wrapTimestamp,
    ) {
    }

    public static function random(SignatureServiceInterface $signatureService): self
    {
        return new self(
            KeyPair::generate($signatureService),
            Timestamp::randomised(),
            Timestamp::randomised(),
        );
    }

    public function getEphemeralKeyPair(): KeyPair
    {
        return $this->ephemeralKeyPair;
    }

    public function getSealTimestamp(): Timestamp
    {
        return $this->sealTimestamp;
    }

    public function getWrapTimestamp(): Timestamp
    {
        return $this->wrapTimestamp;
    }
}
