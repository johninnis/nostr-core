<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Tag;

use Innis\Nostr\Core\Domain\ValueObject\Identity\EventId;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use Innis\Nostr\Core\Domain\ValueObject\Protocol\RelayUrl;
use InvalidArgumentException;

final readonly class Tag
{
    /**
     * @param list<string> $values
     */
    private function __construct(
        private TagType $type,
        private array $values,
    ) {
    }

    public static function tryFromArray(mixed $data): ?self
    {
        if (!is_array($data) || !array_is_list($data)) {
            return null;
        }

        $strings = array_values(array_filter($data, is_string(...)));

        if ([] === $strings || count($strings) !== count($data)) {
            return null;
        }

        if (!array_all($strings, static fn (string $value): bool => mb_check_encoding($value, 'UTF-8'))) {
            return null;
        }

        $type = TagType::tryFromString(array_shift($strings));

        return null === $type ? null : new self($type, $strings);
    }

    /**
     * @param list<string> $data
     */
    public static function fromArray(array $data): self
    {
        return self::tryFromArray($data) ?? throw new InvalidArgumentException('A tag is one or more UTF-8 strings');
    }

    public static function event(EventId $eventId, ?RelayUrl $relayUrl = null, ?PublicKey $author = null): self
    {
        return self::withRelayHint(TagType::EVENT, $eventId->toHex(), $relayUrl, $author?->toHex());
    }

    public static function rootEvent(EventId $eventId, ?RelayUrl $relayUrl = null, ?PublicKey $author = null): self
    {
        return self::withRelayHint(TagType::ROOT_EVENT, $eventId->toHex(), $relayUrl, $author?->toHex());
    }

    public static function pubkey(PublicKey $pubkey, ?RelayUrl $relayUrl = null, ?string $petname = null): self
    {
        return self::withRelayHint(TagType::PUBKEY, $pubkey->toHex(), $relayUrl, $petname);
    }

    // Deliberate: takes a Hashtag where its neighbours take a string, because the lowercase rule lives in the value object and a string overload here would be a second answer to what a hashtag is — see ADR-0068
    public static function hashtag(Hashtag $hashtag): self
    {
        return self::fromArray([TagType::HASHTAG, (string) $hashtag]);
    }

    public static function identifier(string $identifier): self
    {
        return self::fromArray([TagType::IDENTIFIER, $identifier]);
    }

    public function getType(): TagType
    {
        return $this->type;
    }

    public function getValue(int $index = 0): ?string
    {
        return $this->values[$index] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function hasValue(string $value): bool
    {
        return in_array($value, $this->values, true);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return [(string) $this->type, ...$this->values];
    }

    public function equals(self $other): bool
    {
        return $this->type->equals($other->type) && $this->values === $other->values;
    }

    private static function withRelayHint(string $name, string $value, ?RelayUrl $relayUrl, ?string $afterRelay): self
    {
        return self::fromArray([
            $name,
            $value,
            ...(null === $relayUrl && null === $afterRelay ? [] : [null === $relayUrl ? '' : (string) $relayUrl]),
            ...(null === $afterRelay ? [] : [$afterRelay]),
        ]);
    }
}
