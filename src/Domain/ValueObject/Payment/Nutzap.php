<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\NutzapProofCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\WebUrl;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class Nutzap implements PaymentReceiptInterface
{
    private const string DEFAULT_UNIT = 'sat';
    private const array MILLISATS_PER_BITCOIN_UNIT = ['sat' => ZapAmount::MILLISATS_PER_SAT, 'msat' => 1];

    private function __construct(
        private PublicKey $senderPubkey,
        private ?PublicKey $recipientPubkey,
        private ?WebUrl $mint,
        private NutzapProofCollection $proofs,
        private ?CashuAmount $cashuAmount,
        private ?ZapAmount $amount,
        private ?string $message,
    ) {
    }

    #[Override]
    public function getSenderPubkey(): PublicKey
    {
        return $this->senderPubkey;
    }

    #[Override]
    public function getRecipientPubkey(): ?PublicKey
    {
        return $this->recipientPubkey;
    }

    public function getMint(): ?WebUrl
    {
        return $this->mint;
    }

    public function getProofs(): NutzapProofCollection
    {
        return $this->proofs;
    }

    /**
     * @return list<string>
     */
    public function getKeysetIds(): array
    {
        $keysetIds = array_filter(
            array_map(static fn (NutzapProof $proof): ?string => $proof->getKeysetId(), $this->proofs->toArray()),
            static fn (?string $keysetId): bool => null !== $keysetId,
        );

        return array_values(array_unique($keysetIds));
    }

    public function getCashuAmount(): ?CashuAmount
    {
        return $this->cashuAmount;
    }

    #[Override]
    public function getAmount(): ?ZapAmount
    {
        return $this->amount;
    }

    #[Override]
    public function getMessage(): ?string
    {
        return $this->message;
    }

    // Deliberate: the amount is read in the unit the proofs state, never converted, and only a sat or msat one is also a bitcoin amount — see nostr-adrs ADR-0080
    public static function tryFromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::NUTZAP)) {
            return null;
        }

        $tags = $event->getTags();
        $unit = $tags->getSoleValueByType(TagType::unit());
        $proofs = NutzapProofCollection::fromStrings($tags->getValuesByType(TagType::proof()));
        $proofAmounts = array_map(static fn (NutzapProof $proof): int => $proof->getAmount(), $proofs->toArray());

        if (SoleTagValueState::Disagreeing === $unit->getState() || array_any($proofAmounts, static fn (int $amount): bool => $amount < 0)) {
            return null;
        }

        if ([] === $proofAmounts) {
            return self::fromParts($event, $proofs, null);
        }

        $total = self::totalWithinTheIntegerRange($proofAmounts);
        $cashuAmount = null === $total ? null : CashuAmount::from($total, $unit->getValue() ?? self::DEFAULT_UNIT);

        return null === $cashuAmount || self::exceedsTheBitcoinCeiling($cashuAmount) ? null : self::fromParts($event, $proofs, $cashuAmount);
    }

    private static function fromParts(Event $event, NutzapProofCollection $proofs, ?CashuAmount $cashuAmount): self
    {
        $tags = $event->getTags();
        $message = (string) $event->getContent();

        return new self(
            $event->getPubkey(),
            $tags->getSolePubkeyByType(TagType::pubkey()),
            WebUrl::tryFromString($tags->getSoleValueByType(TagType::fromString(TagType::MINT))->getValue()),
            $proofs,
            $cashuAmount,
            null === $cashuAmount ? null : self::bitcoinAmountOf($cashuAmount),
            '' === $message ? null : $message,
        );
    }

    private static function exceedsTheBitcoinCeiling(CashuAmount $cashuAmount): bool
    {
        $millisatsPerUnit = self::millisatsPerUnit($cashuAmount);

        return null !== $millisatsPerUnit && $cashuAmount->getAmount() > intdiv(ZapAmount::MAX_MILLISATS, $millisatsPerUnit);
    }

    private static function bitcoinAmountOf(CashuAmount $cashuAmount): ?ZapAmount
    {
        $millisatsPerUnit = self::millisatsPerUnit($cashuAmount);

        return null === $millisatsPerUnit ? null : ZapAmount::fromMillisats($cashuAmount->getAmount() * $millisatsPerUnit);
    }

    private static function millisatsPerUnit(CashuAmount $cashuAmount): ?int
    {
        return self::MILLISATS_PER_BITCOIN_UNIT[$cashuAmount->getUnit()] ?? null;
    }

    /**
     * @param non-empty-list<int> $proofAmounts
     */
    private static function totalWithinTheIntegerRange(array $proofAmounts): ?int
    {
        return array_reduce(
            $proofAmounts,
            static fn (?int $total, int $amount): ?int => null === $total || $amount > PHP_INT_MAX - $total ? null : $total + $amount,
            0,
        );
    }
}
