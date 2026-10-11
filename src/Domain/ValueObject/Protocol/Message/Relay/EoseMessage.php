<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\Relay;

use Innis\Nostr\Core\Domain\Enum\EoseHint;
use Innis\Nostr\Core\Domain\Enum\RelayMessageType;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\Message\RelayMessage;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\SubscriptionId;
use Override;

final readonly class EoseMessage extends RelayMessage
{
    /**
     * @param list<string> $hints
     */
    public function __construct(private SubscriptionId $subscriptionId, private array $hints = [])
    {
    }

    #[Override]
    public static function type(): RelayMessageType
    {
        return RelayMessageType::Eose;
    }

    public static function hinting(SubscriptionId $subscriptionId, EoseHint ...$hints): self
    {
        return new self($subscriptionId, array_map(static fn (EoseHint $hint): string => $hint->value, array_values($hints)));
    }

    public function getSubscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    /**
     * @return list<string>
     */
    public function getHints(): array
    {
        return $this->hints;
    }

    public function hasHint(EoseHint $hint): bool
    {
        return in_array($hint->value, $this->hints, true);
    }

    #[Override]
    protected function toPayload(): array
    {
        return [(string) $this->subscriptionId, ...([] === $this->hints ? [] : [$this->hints])];
    }

    #[Override]
    protected static function tryFromPayload(array $payload): ?static
    {
        if ([] === $payload) {
            return null;
        }

        $subscriptionId = SubscriptionId::tryFromString($payload[0]);

        return null === $subscriptionId ? null : new self($subscriptionId, self::hintsOf($payload[1] ?? null));
    }

    /**
     * @return list<string>
     */
    // Deliberate: a third element that is no array of strings is ignored, as any extension is, and an unknown hint is kept — see nostr-adrs ADR-0091
    private static function hintsOf(mixed $element): array
    {
        return is_array($element) && array_is_list($element) && array_all($element, static fn (mixed $hint): bool => is_string($hint)) ? $element : [];
    }
}
