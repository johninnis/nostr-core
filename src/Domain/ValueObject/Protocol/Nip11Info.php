<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;
use Innis\Nostr\Core\Domain\ValueObject\Identity\PublicKey;

// Deliberate: a thin typed view over the raw NIP-11 document, not a fully-parsed value object — see ADR-0036
final readonly class Nip11Info
{
    /**
     * @param array<string, mixed> $rawData
     */
    private function __construct(
        private RelayUrl $relayUrl,
        private array $rawData = [],
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
        $pubkey = JsonWireFormat::stringField($this->rawData, 'pubkey');

        return null === $pubkey ? null : PublicKey::fromHex($pubkey);
    }

    public function getContact(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'contact');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getSupportedNips(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'supported_nips');
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
     * @param array<string, mixed> $data
     */
    public static function fromArray(RelayUrl $relayUrl, array $data = []): self
    {
        return new self($relayUrl, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->rawData;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getLimitation(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'limitation');
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
        return true === ($this->getLimitation()['auth_required'] ?? false);
    }

    public function isPaymentRequired(): bool
    {
        return true === ($this->getLimitation()['payment_required'] ?? false);
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getRetention(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'retention');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getRelayCountries(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'relay_countries');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getLanguageTags(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'language_tags');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getTags(): ?array
    {
        return JsonWireFormat::arrayField($this->rawData, 'tags');
    }

    public function getPostingPolicy(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'posting_policy');
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
        return JsonWireFormat::arrayField($this->rawData, 'fees');
    }

    public function getPrivacyPolicy(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'privacy_policy');
    }

    public function getTermsOfService(): ?string
    {
        return JsonWireFormat::stringField($this->rawData, 'terms_of_service');
    }
}
