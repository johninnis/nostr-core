<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Override;

final readonly class RandomGiftWrapEnvelopeFactory implements GiftWrapEnvelopeFactoryInterface
{
    public function __construct(
        private SignatureServiceInterface $signatureService,
    ) {
    }

    #[Override]
    public function create(): GiftWrapEnvelope
    {
        return GiftWrapEnvelope::random($this->signatureService);
    }
}
