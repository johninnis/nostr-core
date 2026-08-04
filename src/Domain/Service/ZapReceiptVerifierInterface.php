<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\ZapReceiptVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

interface ZapReceiptVerifierInterface
{
    /**
     * Applies NIP-57 Appendix F to a zap receipt, returning null when it passes.
     *
     * The caller supplies `$lnurlProviderPubkey` — the `nostrPubkey` from the recipient's LNURL
     * endpoint configuration — because this package never fetches LNURL configuration. That value is
     * the root of trust: without the right one, a receipt from any provider verifies.
     */
    public function verify(
        Event $receipt,
        PublicKey $lnurlProviderPubkey,
        ?string $expectedLnurl = null,
    ): ?ZapReceiptVerificationFailure;
}
