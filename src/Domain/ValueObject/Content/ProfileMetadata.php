<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Content;

use Innis\Nostr\Core\Domain\Service\JsonWireFormat;

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

    public static function fromJsonString(string $json): ?self
    {
        $data = JsonWireFormat::decodeArray($json);

        return null === $data ? null : self::fromArray($data);
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            JsonWireFormat::stringField($data, 'name'),
            JsonWireFormat::stringField($data, 'display_name'),
            JsonWireFormat::stringField($data, 'about'),
            JsonWireFormat::stringField($data, 'picture'),
            JsonWireFormat::stringField($data, 'banner'),
            JsonWireFormat::stringField($data, 'website'),
            JsonWireFormat::stringField($data, 'nip05'),
            JsonWireFormat::stringField($data, 'lud16'),
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
