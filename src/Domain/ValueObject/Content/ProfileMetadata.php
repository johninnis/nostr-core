<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

final readonly class ProfileMetadata
{
    public function __construct(
        private ?string $name,
        private ?string $displayName,
        private ?string $about,
        private ?string $picture,
        private ?string $banner,
        private ?string $website,
        private ?string $nip05,
        private ?string $lud16,
    ) {
    }

    public static function fromJsonString(string $json): self
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return new self(null, null, null, null, null, null, null, null);
        }

        return new self(
            self::extractString($data, 'name'),
            self::extractString($data, 'display_name'),
            self::extractString($data, 'about'),
            self::extractString($data, 'picture'),
            self::extractString($data, 'banner'),
            self::extractString($data, 'website'),
            self::extractString($data, 'nip05'),
            self::extractString($data, 'lud16'),
        );
    }

    private static function extractString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function getAbout(): ?string
    {
        return $this->about;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getNip05(): ?string
    {
        return $this->nip05;
    }

    public function getLud16(): ?string
    {
        return $this->lud16;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'about' => $this->about,
            'picture' => $this->picture,
            'banner' => $this->banner,
            'website' => $this->website,
            'nip05' => $this->nip05,
            'lud16' => $this->lud16,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? null,
            $data['display_name'] ?? null,
            $data['about'] ?? null,
            $data['picture'] ?? null,
            $data['banner'] ?? null,
            $data['website'] ?? null,
            $data['nip05'] ?? null,
            $data['lud16'] ?? null,
        );
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->displayName === $other->displayName
            && $this->about === $other->about
            && $this->picture === $other->picture
            && $this->banner === $other->banner
            && $this->website === $other->website
            && $this->nip05 === $other->nip05
            && $this->lud16 === $other->lud16;
    }
}
