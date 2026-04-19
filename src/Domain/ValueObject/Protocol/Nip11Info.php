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
            name: self::stringOrNull($data, 'name'),
            description: self::stringOrNull($data, 'description'),
            pubkey: self::stringOrNull($data, 'pubkey'),
            contact: self::stringOrNull($data, 'contact'),
            supportedNips: self::arrayOrNull($data, 'supported_nips'),
            software: self::stringOrNull($data, 'software'),
            version: self::stringOrNull($data, 'version'),
            banner: self::stringOrNull($data, 'banner'),
            icon: self::stringOrNull($data, 'icon'),
            rawData: $data,
        );
    }

    private static function stringOrNull(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private static function arrayOrNull(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
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
        ], static fn ($value) => null !== $value);
    }

    public function getLimitation(): ?array
    {
        $value = $this->rawData['limitation'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getMaxSubscriptions(): ?int
    {
        $value = $this->getLimitation()['max_subscriptions'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function getMaxLimit(): ?int
    {
        $value = $this->getLimitation()['max_limit'] ?? null;

        return is_int($value) ? $value : null;
    }

    public function isAuthRequired(): bool
    {
        $value = $this->getLimitation()['auth_required'] ?? false;

        return true === $value;
    }

    public function isPaymentRequired(): bool
    {
        $value = $this->getLimitation()['payment_required'] ?? false;

        return true === $value;
    }

    public function getRetention(): ?array
    {
        $value = $this->rawData['retention'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getRelayCountries(): ?array
    {
        $value = $this->rawData['relay_countries'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getLanguageTags(): ?array
    {
        $value = $this->rawData['language_tags'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getTags(): ?array
    {
        $value = $this->rawData['tags'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getPostingPolicy(): ?string
    {
        $value = $this->rawData['posting_policy'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getPaymentsUrl(): ?string
    {
        $value = $this->rawData['payments_url'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getFees(): ?array
    {
        $value = $this->rawData['fees'] ?? null;

        return is_array($value) ? $value : null;
    }

    public function getPrivacyPolicy(): ?string
    {
        $value = $this->rawData['privacy_policy'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getTermsOfService(): ?string
    {
        $value = $this->rawData['terms_of_service'] ?? null;

        return is_string($value) ? $value : null;
    }
}
