<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Tests\Integration\Domain\Service;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Failure\ZapReceiptVerificationFailure;
use Innis\Nostr\Core\Domain\Service\SignatureServiceInterface;
use Innis\Nostr\Core\Domain\Service\ZapReceiptVerifier;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventContent;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\KeyPair;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use PHPUnit\Framework\TestCase;

final class ZapReceiptVerifierTest extends TestCase
{
    private const string INVOICE_1000_SATS = 'lnbc10u1p3unwfusp5t9r3yf';

    private SignatureServiceInterface $signer;
    private ZapReceiptVerifier $verifier;
    private KeyPair $provider;
    private KeyPair $sender;

    protected function setUp(): void
    {
        $this->signer = Secp256k1Signer::create();
        $this->verifier = new ZapReceiptVerifier($this->signer);
        $this->provider = KeyPair::generate($this->signer);
        $this->sender = KeyPair::generate($this->signer);
    }

    public function testAWellFormedReceiptVerifies(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender));

        $this->assertNull($this->verifier->verify($receipt, $this->provider->getPublicKey()));
    }

    // NIP-57 Appendix F: the receipt's pubkey MUST be the recipient lnurl provider's nostrPubkey.
    public function testAReceiptFromAnotherProviderIsRejected(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender));
        $someoneElse = KeyPair::generate($this->signer)->getPublicKey();

        $this->assertSame(
            ZapReceiptVerificationFailure::ProviderPubkeyMismatch,
            $this->verifier->verify($receipt, $someoneElse),
        );
    }

    // NIP-57 Appendix F: the bolt11 invoiceAmount MUST equal the zap request's amount tag.
    public function testAnAmountDisagreeingWithTheInvoiceIsRejected(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender, amountMillisats: 999_999));

        $this->assertSame(
            ZapReceiptVerificationFailure::AmountMismatch,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    // NIP-57 Appendix F: the zap request's lnurl tag SHOULD equal the recipient's lnurl.
    public function testAnLnurlDisagreeingWithTheRecipientIsRejected(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender, lnurl: 'lnurl1someoneelse'));

        $this->assertSame(
            ZapReceiptVerificationFailure::LnurlMismatch,
            $this->verifier->verify($receipt, $this->provider->getPublicKey(), 'lnurl1therecipient'),
        );
    }

    public function testALnurlTagMatchingTheRecipientIsAccepted(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender, lnurl: 'lnurl1therecipient'));

        $this->assertNull($this->verifier->verify($receipt, $this->provider->getPublicKey(), 'lnurl1therecipient'));
    }

    // Deliberate: not an Appendix F requirement, but the sender a consumer displays comes from this request; without the check a trusted provider could attribute a zap to anyone
    public function testAZapRequestNotSignedByItsClaimedSenderIsRejected(): void
    {
        $forged = $this->forgedZapRequestJson();
        $receipt = $this->receiptFromDescription($forged);

        $this->assertSame(
            ZapReceiptVerificationFailure::ZapRequestSignatureInvalid,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    public function testAReceiptWithNoDescriptionTagIsRejected(): void
    {
        $receipt = $this->signedEvent(
            $this->provider,
            EventKind::ZAP_RECEIPT,
            new TagCollection([Tag::create((string) TagType::bolt11(), self::INVOICE_1000_SATS)]),
        );

        $this->assertSame(
            ZapReceiptVerificationFailure::MissingZapRequest,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    public function testADescriptionThatIsNotAnEventIsRejected(): void
    {
        $receipt = $this->receiptFromDescription('{"not":"an event"}');

        $this->assertSame(
            ZapReceiptVerificationFailure::MalformedZapRequest,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    public function testANonReceiptKindIsRejected(): void
    {
        $notAReceipt = $this->signedEvent($this->provider, EventKind::TEXT_NOTE, new TagCollection());

        $this->assertSame(
            ZapReceiptVerificationFailure::WrongKind,
            $this->verifier->verify($notAReceipt, $this->provider->getPublicKey()),
        );
    }

    public function testAReceiptWithNoReadableInvoiceAmountIsRejected(): void
    {
        $receipt = $this->signedEvent(
            $this->provider,
            EventKind::ZAP_RECEIPT,
            new TagCollection([
                Tag::create((string) TagType::bolt11(), 'lnbc1notanamount'),
                Tag::create((string) TagType::description(), $this->zapRequest($this->sender)),
            ]),
        );

        $this->assertSame(
            ZapReceiptVerificationFailure::MissingInvoiceAmount,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    // Deliberate: a duplicate description tag is ambiguous, not resolvable by position — pinning the rejection stops it being "fixed" back to picking the first
    public function testAReceiptCarryingTwoDescriptionTagsIsRejected(): void
    {
        $receipt = $this->signedEvent(
            $this->provider,
            EventKind::ZAP_RECEIPT,
            new TagCollection([
                Tag::create((string) TagType::bolt11(), self::INVOICE_1000_SATS),
                Tag::create((string) TagType::description(), '{"not":"an event"}'),
                Tag::create((string) TagType::description(), $this->zapRequest($this->sender)),
            ]),
        );

        $this->assertSame(
            ZapReceiptVerificationFailure::MultipleZapRequests,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    private function receipt(string $zapRequestJson): Event
    {
        return $this->receiptFromDescription($zapRequestJson);
    }

    private function receiptFromDescription(string $description): Event
    {
        return $this->signedEvent(
            $this->provider,
            EventKind::ZAP_RECEIPT,
            new TagCollection([
                Tag::create((string) TagType::bolt11(), self::INVOICE_1000_SATS),
                Tag::create((string) TagType::description(), $description),
            ]),
        );
    }

    private function zapRequest(KeyPair $sender, int $amountMillisats = 1_000_000, ?string $lnurl = null): string
    {
        $tags = [Tag::create((string) TagType::amount(), (string) $amountMillisats)];

        if (null !== $lnurl) {
            $tags[] = Tag::create((string) TagType::lnurl(), $lnurl);
        }

        return $this->signedEvent($sender, EventKind::ZAP_REQUEST, new TagCollection($tags))->toJson();
    }

    private function forgedZapRequestJson(): string
    {
        $genuine = $this->signedEvent($this->sender, EventKind::ZAP_REQUEST, new TagCollection([
            Tag::create((string) TagType::amount(), '1000000'),
        ]))->toArray();

        $genuine['pubkey'] = KeyPair::generate($this->signer)->getPublicKey()->toHex();

        return (string) json_encode($genuine);
    }

    private function signedEvent(KeyPair $keyPair, int $kind, TagCollection $tags): Event
    {
        return new Rumour(
            $keyPair->getPublicKey(),
            Timestamp::now(),
            EventKind::fromInt($kind),
            $tags,
            EventContent::fromString(''),
        )->sign($keyPair, $this->signer);
    }
}
