<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Infrastructure\Crypto;

interface GiftWrapEnvelopeFactoryInterface
{
    public function create(): GiftWrapEnvelope;
}
