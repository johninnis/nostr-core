<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Payment;

use Innis\Nostr\Core\Domain\Collection\TagCollection;
use Innis\Nostr\Core\Domain\Entity\Event;
use Innis\Nostr\Core\Domain\Enum\SoleTagValueState;
use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Content\EventKind;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Tag\TagType;
use Override;

final readonly class Nutzap implements PaymentReceiptInterface
{
    private const string DEFAULT_UNIT = 'sat';
    private const array MILLISATS_PER_BITCOIN_UNIT = ['sat' => ZapAmount::MILLISATS_PER_SAT, 'msat' => 1];

    private function __construct(
        private PublicKey $senderPubkey,
        private ?PublicKey $recipientPubkey,
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

    public static function tryFromEvent(Event $event): ?self
    {
        if (!$event->getKind()->is(EventKind::NUTZAP)) {
            return null;
        }

        $tags = $event->getTags();
        $unit = $tags->getSoleValueByType(TagType::unit());
        $proofAmounts = self::extractProofAmounts($tags);

        if (SoleTagValueState::Disagreeing === $unit->getState() || array_any($proofAmounts, static fn (int $amount): bool => $amount < 0)) {
            return null;
        }

        $millisatsPerUnit = self::MILLISATS_PER_BITCOIN_UNIT[$unit->getValue() ?? self::DEFAULT_UNIT] ?? null;
        if (null === $millisatsPerUnit || [] === $proofAmounts) {
            return self::withAmount($event, null);
        }

        $totalMillisats = self::totalMillisatsWithinCap($proofAmounts, $millisatsPerUnit);

        return null === $totalMillisats ? null : self::withAmount($event, ZapAmount::fromMillisats($totalMillisats));
    }

    private static function withAmount(Event $event, ?ZapAmount $amount): self
    {
        $message = (string) $event->getContent();

        return new self(
            $event->getPubkey(),
            $event->getTags()->getSolePubkeyByType(TagType::pubkey()),
            $amount,
            '' === $message ? null : $message,
        );
    }

    /**
     * @return list<int>
     */
    private static function extractProofAmounts(TagCollection $tags): array
    {
        return array_values(array_filter(
            array_map(self::proofAmount(...), $tags->getValuesByType(TagType::proof())),
            static fn (?int $amount): bool => null !== $amount,
        ));
    }

    private static function proofAmount(string $proofJson): ?int
    {
        $proof = JsonWireFormat::decodeObject($proofJson);

        return null === $proof ? null : JsonWireFormat::intField($proof, 'amount');
    }

    /**
     * @param non-empty-list<int> $proofAmounts
     */
    private static function totalMillisatsWithinCap(array $proofAmounts, int $millisatsPerUnit): ?int
    {
        $maxTotal = intdiv(ZapAmount::MAX_MILLISATS, $millisatsPerUnit);
        $total = array_reduce(
            $proofAmounts,
            static fn (?int $total, int $amount): ?int => null === $total || $amount > $maxTotal - $total ? null : $total + $amount,
            0,
        );

        return null === $total ? null : $total * $millisatsPerUnit;
    }
}
