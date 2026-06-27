<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

// Deliberate: a single-consumer, intra-concern injection seam, not a shared port, so it is defined here beside its implementation — see ADR-0035
interface GiftWrapEnvelopeFactoryInterface
{
    public function create(): GiftWrapEnvelope;
}
