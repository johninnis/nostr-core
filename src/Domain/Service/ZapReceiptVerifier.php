<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\Service;

use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\ZapReceiptVerificationFailure;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapAmount;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class ZapReceiptVerifier implements ZapReceiptVerifierInterface
{
    public function __construct(private SignatureServiceInterface $signatureService)
    {
    }

    // Deliberate: structural checks run before the two signature verifications, so a malformed receipt is rejected without paying for elliptic-curve work — the same ordering EventValidator uses — see ADR-0062
    #[Override]
    public function verify(
        Event $receipt,
        PublicKey $lnurlProviderPubkey,
        ?string $expectedLnurl = null,
    ): ?ZapReceiptVerificationFailure {
        if (!$receipt->getKind()->is(EventKind::ZAP_RECEIPT)) {
            return ZapReceiptVerificationFailure::WrongKind;
        }

        // NIP-57 Appendix F: the receipt's pubkey MUST be the recipient lnurl provider's nostrPubkey.
        if (!$receipt->getPubkey()->equals($lnurlProviderPubkey)) {
            return ZapReceiptVerificationFailure::ProviderPubkeyMismatch;
        }

        $descriptions = $receipt->getTags()->getValuesByType(TagType::description());

        if ([] === $descriptions) {
            return ZapReceiptVerificationFailure::MissingZapRequest;
        }

        // Deliberate: a second description tag is named as malformed rather than resolved by position; picking the first would let a receipt carry one request for this verifier and another for whatever reads it next — the same reason Nip98Validator rejects duplicate url, method and payload tags
        if (1 !== count($descriptions)) {
            return ZapReceiptVerificationFailure::MultipleZapRequests;
        }

        $zapRequest = Event::tryFromJson($descriptions[0]);

        if (null === $zapRequest) {
            return ZapReceiptVerificationFailure::MalformedZapRequest;
        }

        if (!$zapRequest->getKind()->is(EventKind::ZAP_REQUEST)) {
            return ZapReceiptVerificationFailure::ZapRequestWrongKind;
        }

        return $this->verifyAmount($receipt, $zapRequest)
            ?? $this->verifyLnurl($zapRequest, $expectedLnurl)
            ?? $this->verifySignatures($receipt, $zapRequest);
    }

    private function verifyAmount(Event $receipt, Event $zapRequest): ?ZapReceiptVerificationFailure
    {
        $invoiceAmount = $this->invoiceAmount($receipt);

        if (null === $invoiceAmount) {
            return ZapReceiptVerificationFailure::MissingInvoiceAmount;
        }

        // NIP-57 Appendix F: the bolt11 invoiceAmount MUST equal the zap request's amount tag.
        $requestAmounts = $zapRequest->getTags()->getValuesByType(TagType::amount());

        foreach ($requestAmounts as $requestAmount) {
            if (!is_numeric($requestAmount) || (int) $requestAmount !== $invoiceAmount->toMillisats()) {
                return ZapReceiptVerificationFailure::AmountMismatch;
            }
        }

        return null;
    }

    // NIP-57 Appendix F: the zap request's lnurl tag, if present, SHOULD equal the recipient's lnurl.
    private function verifyLnurl(Event $zapRequest, ?string $expectedLnurl): ?ZapReceiptVerificationFailure
    {
        if (null === $expectedLnurl) {
            return null;
        }

        foreach ($zapRequest->getTags()->getValuesByType(TagType::lnurl()) as $lnurl) {
            if (0 !== strcasecmp($lnurl, $expectedLnurl)) {
                return ZapReceiptVerificationFailure::LnurlMismatch;
            }
        }

        return null;
    }

    // Deliberate: the zap request's own signature is checked as well as the receipt's; NIP-57 does not list it in Appendix F, but the sender a consumer displays comes from that request, and without this a trusted provider could attribute a zap to anyone — see ADR-0062
    private function verifySignatures(Event $receipt, Event $zapRequest): ?ZapReceiptVerificationFailure
    {
        if (!$receipt->verify($this->signatureService)) {
            return ZapReceiptVerificationFailure::ReceiptSignatureInvalid;
        }

        if (!$zapRequest->verify($this->signatureService)) {
            return ZapReceiptVerificationFailure::ZapRequestSignatureInvalid;
        }

        return null;
    }

    private function invoiceAmount(Event $receipt): ?ZapAmount
    {
        foreach ($receipt->getTags()->getValuesByType(TagType::bolt11()) as $value) {
            $parsed = ZapAmount::tryFromBolt11($value);

            if (null !== $parsed) {
                return $parsed;
            }
        }

        return null;
    }
}
