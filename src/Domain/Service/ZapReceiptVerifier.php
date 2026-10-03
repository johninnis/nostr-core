<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\ZapReceiptVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class ZapReceiptVerifier implements ZapReceiptVerifierInterface
{
    public function __construct(private SignatureServiceInterface $signatureService)
    {
    }

    // Deliberate: the comparisons run before the two signature verifications, so a receipt from the wrong provider is rejected without paying for elliptic-curve work — see ADR-0079
    #[Override]
    public function verify(
        ZapReceipt $receipt,
        PublicKey $lnurlProviderPubkey,
        ?string $expectedLnurl = null,
    ): ?ZapReceiptVerificationFailure {
        if (!$receipt->getReceipt()->getPubkey()->equals($lnurlProviderPubkey)) {
            return ZapReceiptVerificationFailure::ProviderPubkeyMismatch;
        }

        return $this->verifyLnurl($receipt->getZapRequest(), $expectedLnurl)
            ?? $this->verifySignatures($receipt);
    }

    private function verifyLnurl(Event $zapRequest, ?string $expectedLnurl): ?ZapReceiptVerificationFailure
    {
        if (null === $expectedLnurl) {
            return null;
        }

        $matches = array_all(
            $zapRequest->getTags()->getValuesByType(TagType::lnurl()),
            static fn (string $lnurl): bool => 0 === strcasecmp($lnurl, $expectedLnurl),
        );

        return $matches ? null : ZapReceiptVerificationFailure::LnurlMismatch;
    }

    // Deliberate: the zap request's own signature is checked as well as the receipt's, because the sender a consumer displays comes from that request — see ADR-0079
    private function verifySignatures(ZapReceipt $receipt): ?ZapReceiptVerificationFailure
    {
        if (!$receipt->getReceipt()->verify($this->signatureService)) {
            return ZapReceiptVerificationFailure::ReceiptSignatureInvalid;
        }

        return $receipt->getZapRequest()->verify($this->signatureService)
            ? null
            : ZapReceiptVerificationFailure::ZapRequestSignatureInvalid;
    }
}
