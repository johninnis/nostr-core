<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;
use stdClass;

// Deliberate: a thin typed view that keeps the raw document and projects fields on access, not an eagerly fully-parsed value object like ProfileMetadata; the relay-info document is open and advisory — see ADR-0036
final readonly class Nip11Info
{
    public const string MEDIA_TYPE = 'application/nostr+json';

    /**
     * @param array<string, mixed> $rawData
     */
    private function __construct(
        private RelayUrl $relayUrl,
        private array $rawData,
    ) {
    }

    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getName(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'name');
    }

    public function getDescription(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'description');
    }

    public function getPubkey(): ?PublicKey
    {
        return $this->publicKeyField('pubkey');
    }

    public function getSelf(): ?PublicKey
    {
        return $this->publicKeyField('self');
    }

    public function getContact(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'contact');
    }

    /**
     * @return list<int>|null
     */
    public function getSupportedNips(): ?array
    {
        return JsonWireFormat::listField($this->rawData, 'supported_nips', static fn (mixed $nip): ?int => is_int($nip) ? $nip : null);
    }

    public function getSoftware(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'software');
    }

    public function getVersion(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'version');
    }

    public function getBanner(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'banner');
    }

    public function getIcon(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'icon');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getLimitation(): ?array
    {
        return JsonWireFormat::objectField($this->rawData, 'limitation');
    }

    public function getMaxSubscriptions(): ?int
    {
        return JsonWireFormat::intField($this->getLimitation() ?? [], 'max_subscriptions');
    }

    public function getMaxLimit(): ?int
    {
        return JsonWireFormat::intField($this->getLimitation() ?? [], 'max_limit');
    }

    public function isAuthRequired(): bool
    {
        return JsonWireFormat::boolField($this->getLimitation() ?? [], 'auth_required') ?? false;
    }

    public function isPaymentRequired(): bool
    {
        return JsonWireFormat::boolField($this->getLimitation() ?? [], 'payment_required') ?? false;
    }

    public function getPaymentsUrl(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'payments_url');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getFees(): ?array
    {
        return JsonWireFormat::objectField($this->rawData, 'fees');
    }

    public function getTermsOfService(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'terms_of_service');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }

    public function toJson(): string
    {
        return JsonWireFormat::encode($this->rawData ?: new stdClass(), JsonWireFormat::MESSAGE);
    }

    private function publicKeyField(string $key): ?PublicKey
    {
        $hex = JsonWireFormat::stringField($this->rawData, $key);

        return null === $hex ? null : PublicKey::tryFromHex($hex);
    }

    public static function tryFromJson(RelayUrl $relayUrl, string $json): ?self
    {
        $fields = JsonWireFormat::decodeObject($json);

        return null === $fields ? null : self::fromArray($relayUrl, array_filter($fields, is_string(...), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(RelayUrl $relayUrl, array $data = []): self
    {
        return new self($relayUrl, $data);
    }
}
