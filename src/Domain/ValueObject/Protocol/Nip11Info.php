<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Protocol;

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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(RelayUrl $relayUrl, array $data = []): self
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
        return $this->arrayOrNull('limitation');
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
        return $this->arrayOrNull('retention');
    }

    public function getRelayCountries(): ?array
    {
        return $this->arrayOrNull('relay_countries');
    }

    public function getLanguageTags(): ?array
    {
        return $this->arrayOrNull('language_tags');
    }

    public function getTags(): ?array
    {
        return $this->arrayOrNull('tags');
    }

    public function getPostingPolicy(): ?string
    {
        return $this->stringOrNull('posting_policy');
    }

    public function getPaymentsUrl(): ?string
    {
        return $this->stringOrNull('payments_url');
    }

    public function getFees(): ?array
    {
        return $this->arrayOrNull('fees');
    }

    public function getPrivacyPolicy(): ?string
    {
        return $this->stringOrNull('privacy_policy');
    }

    public function getTermsOfService(): ?string
    {
        return $this->stringOrNull('terms_of_service');
    }
}
