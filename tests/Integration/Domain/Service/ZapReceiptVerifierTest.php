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
use Innis\Nostr\Core\Domain\ValueObject\Payment\ZapReceipt;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Rumour;
use Innis\Nostr\Core\Domain\ValueObject\Tag\Tag;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Innis\Nostr\Core\Domain\ValueObject\Timestamp;
use Innis\Nostr\Core\Infrastructure\Crypto\Secp256k1Signer;
use Innis\Nostr\Core\Tests\Support\EventMother;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

    public function testAReceiptFromAnotherProviderIsRejected(): void
    {
        $receipt = $this->receipt($this->zapRequest($this->sender));
        $someoneElse = KeyPair::generate($this->signer)->getPublicKey();

        $this->assertSame(
            ZapReceiptVerificationFailure::ProviderPubkeyMismatch,
            $this->verifier->verify($receipt, $someoneElse),
        );
    }

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

    // Deliberate: the zap request's own signature is checked because the sender a consumer displays comes from it — see ADR-0079
    public function testAZapRequestNotSignedByItsClaimedSenderIsRejected(): void
    {
        $forged = $this->forgedZapRequestJson();
        $receipt = $this->receiptFromDescription($forged);

        $this->assertSame(
            ZapReceiptVerificationFailure::ZapRequestSignatureInvalid,
            $this->verifier->verify($receipt, $this->provider->getPublicKey()),
        );
    }

    public function testAReceiptNotSignedByItsAuthorIsRejected(): void
    {
        $genuine = $this->receiptEvent($this->zapRequest($this->sender));
        $tampered = ZapReceipt::tryFromEvent(new Event($genuine->getRumour(), $genuine->getId(), EventMother::signature()))
            ?? throw new RuntimeException('Expected a parsable zap receipt');

        $this->assertSame(
            ZapReceiptVerificationFailure::ReceiptSignatureInvalid,
            $this->verifier->verify($tampered, $this->provider->getPublicKey()),
        );
    }

    private function receipt(string $zapRequestJson): ZapReceipt
    {
        return $this->receiptFromDescription($zapRequestJson);
    }

    private function receiptFromDescription(string $description): ZapReceipt
    {
        return ZapReceipt::tryFromEvent($this->receiptEvent($description)) ?? throw new RuntimeException('Expected a parsable zap receipt');
    }

    private function receiptEvent(string $description): Event
    {
        return $this->signedEvent(
            $this->provider,
            EventKind::ZAP_RECEIPT,
            new TagCollection([
                Tag::fromArray([TagType::BOLT11, self::INVOICE_1000_SATS]),
                Tag::fromArray([TagType::DESCRIPTION, $description]),
            ]),
        );
    }

    private function zapRequest(KeyPair $sender, int $amountMillisats = 1_000_000, ?string $lnurl = null): string
    {
        $tags = [Tag::fromArray([TagType::AMOUNT, (string) $amountMillisats])];

        if (null !== $lnurl) {
            $tags[] = Tag::fromArray([TagType::LNURL, $lnurl]);
        }

        return $this->signedEvent($sender, EventKind::ZAP_REQUEST, new TagCollection($tags))->toJson();
    }

    private function forgedZapRequestJson(): string
    {
        $genuine = $this->signedEvent($this->sender, EventKind::ZAP_REQUEST, new TagCollection([
            Tag::fromArray([TagType::AMOUNT, '1000000']),
        ]))->toArray();

        $genuine['pubkey'] = KeyPair::generate($this->signer)->getPublicKey()->toHex();

        return (string) json_encode($genuine);
    }

    private function signedEvent(KeyPair $keyPair, int $kind, TagCollection $tags): Event
    {
        return Rumour::draft(
            $keyPair->getPublicKey(),
            EventKind::fromInt($kind),
            EventContent::fromString(''),
            $tags,
            Timestamp::now(),
        )->sign($keyPair, $this->signer);
    }
}
