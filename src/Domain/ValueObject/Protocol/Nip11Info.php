<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

final readonly class Nip11Info
{
    public function __construct(
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
        return $this->stringOrNull('name');
    }

    public function getDescription(): ?string
    {
        return $this->stringOrNull('description');
    }

    public function getPubkey(): ?string
    {
        return $this->stringOrNull('pubkey');
    }

    public function getContact(): ?string
    {
        return $this->stringOrNull('contact');
    }

    public function getSupportedNips(): ?array
    {
        return $this->arrayOrNull('supported_nips');
    }

    public function getSoftware(): ?string
    {
        return $this->stringOrNull('software');
    }

    public function getVersion(): ?string
    {
        return $this->stringOrNull('version');
    }

    public function getBanner(): ?string
    {
        return $this->stringOrNull('banner');
    }

    public function getIcon(): ?string
    {
        return $this->stringOrNull('icon');
    }

    public static function fromArray(RelayUrl $relayUrl, array $data): self
    {
        return new self($relayUrl, $data);
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->rawData[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    private function arrayOrNull(string $key): ?array
    {
        $value = $this->rawData[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    public function toArray(): array
    {
        return $this->rawData;
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
