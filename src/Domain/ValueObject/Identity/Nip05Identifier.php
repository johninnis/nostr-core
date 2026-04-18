<?php

declare(strict_types=1);

namespace Innis\Nostr\Core\Domain\ValueObject\Identity;

use InvalidArgumentException;

final readonly class Nip05Identifier
{
    private const LOCAL_PART_PATTERN = '/^[A-Za-z0-9._-]+$/';
    private const DOMAIN_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i';

    public function __construct(
        private string $localPart,
        private string $domain,
    ) {
    }

    public static function fromString(string $identifier): self
    {
        $parts = explode('@', $identifier, 2);

        if (2 !== count($parts)) {
            throw new InvalidArgumentException('Invalid NIP-05 identifier format. Expected: name@domain.com');
        }

        $localPart = trim($parts[0]);
        $domain = trim($parts[1]);

        if ('' === $localPart || '' === $domain) {
            throw new InvalidArgumentException('NIP-05 identifier cannot have empty local part or domain');
        }

        if (!preg_match(self::LOCAL_PART_PATTERN, $localPart)) {
            throw new InvalidArgumentException('NIP-05 local part contains disallowed characters');
        }

        if (!preg_match(self::DOMAIN_PATTERN, $domain)) {
            throw new InvalidArgumentException('NIP-05 domain is not a valid hostname');
        }

        if (false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('NIP-05 domain must be a hostname, not an IP literal');
        }

        return new self($localPart, $domain);
    }

    public function getLocalPart(): string
    {
        return $this->localPart;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getWellKnownUrl(): string
    {
        return sprintf(
            'https://%s/.well-known/nostr.json?name=%s',
            $this->domain,
            rawurlencode($this->localPart),
        );
    }

    public function __toString(): string
    {
        return $this->localPart.'@'.$this->domain;
    }
}
