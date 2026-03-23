<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class Nip11Info
{
    public function __construct(
        private RelayUrl $relayUrl,
        private ?string $name = null,
        private ?string $description = null,
        private ?string $pubkey = null,
        private ?string $contact = null,
        private ?array $supportedNips = null,
        private ?string $software = null,
        private ?string $version = null,
        private ?string $banner = null,
        private ?string $icon = null,
        private ?array $rawData = null,
    ) {
    }

    public function getRelayUrl(): RelayUrl
    {
        return $this->relayUrl;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPubkey(): ?string
    {
        return $this->pubkey;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function getSupportedNips(): ?array
    {
        return $this->supportedNips;
    }

    public function getSoftware(): ?string
    {
        return $this->software;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public static function fromArray(RelayUrl $relayUrl, array $data): self
    {
        return new self(
            relayUrl: $relayUrl,
            name: $data['name'] ?? null,
            description: $data['description'] ?? null,
            pubkey: $data['pubkey'] ?? null,
            contact: $data['contact'] ?? null,
            supportedNips: $data['supported_nips'] ?? null,
            software: $data['software'] ?? null,
            version: $data['version'] ?? null,
            banner: $data['banner'] ?? null,
            icon: $data['icon'] ?? null,
            rawData: $data,
        );
    }

    public function toArray(): array
    {
        return $this->rawData ?? array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'pubkey' => $this->pubkey,
            'contact' => $this->contact,
            'supported_nips' => $this->supportedNips,
            'software' => $this->software,
            'version' => $this->version,
            'banner' => $this->banner,
            'icon' => $this->icon,
        ], fn ($value) => $value !== null);
    }

    public function getLimitation(): ?array
    {
        return $this->rawData['limitation'] ?? null;
    }

    public function getMaxSubscriptions(): ?int
    {
        return $this->getLimitation()['max_subscriptions'] ?? null;
    }

    public function getMaxLimit(): ?int
    {
        return $this->getLimitation()['max_limit'] ?? null;
    }

    public function isAuthRequired(): bool
    {
        return $this->getLimitation()['auth_required'] ?? false;
    }

    public function isPaymentRequired(): bool
    {
        return $this->getLimitation()['payment_required'] ?? false;
    }

    public function getRetention(): ?array
    {
        return $this->rawData['retention'] ?? null;
    }

    public function getRelayCountries(): ?array
    {
        return $this->rawData['relay_countries'] ?? null;
    }

    public function getLanguageTags(): ?array
    {
        return $this->rawData['language_tags'] ?? null;
    }

    public function getTags(): ?array
    {
        return $this->rawData['tags'] ?? null;
    }

    public function getPostingPolicy(): ?string
    {
        return $this->rawData['posting_policy'] ?? null;
    }

    public function getPaymentsUrl(): ?string
    {
        return $this->rawData['payments_url'] ?? null;
    }

    public function getFees(): ?array
    {
        return $this->rawData['fees'] ?? null;
    }

    public function getPrivacyPolicy(): ?string
    {
        return $this->rawData['privacy_policy'] ?? null;
    }

    public function getTermsOfService(): ?string
    {
        return $this->rawData['terms_of_service'] ?? null;
    }
}
