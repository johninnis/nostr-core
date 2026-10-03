<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Failure\ZapReceiptVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;

interface ZapReceiptVerifierInterface
{
    // Deliberate: the LNURL provider key is the caller's to supply and is the root of trust; this package never fetches LNURL configuration — see ADR-0079
    public function verify(
        ZapReceipt $receipt,
        PublicKey $lnurlProviderPubkey,
        ?string $expectedLnurl = null,
    ): ?ZapReceiptVerificationFailure;
}
